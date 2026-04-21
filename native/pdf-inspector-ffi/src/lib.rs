use libc::c_char;
use std::collections::HashSet;
use std::ffi::{CStr, CString};
use std::panic;
use std::slice;

// ---------------------------------------------------------------------------
// Helpers: JSON envelope & error mapping
// ---------------------------------------------------------------------------

fn ok_json(data: serde_json::Value) -> *mut c_char {
    let envelope = serde_json::json!({"ok": true, "data": data});
    let s = envelope.to_string();
    CString::new(s).unwrap().into_raw()
}

fn err_json(code: &str, message: &str) -> *mut c_char {
    let envelope = serde_json::json!({
        "ok": false,
        "error": {"code": code, "message": message}
    });
    let s = envelope.to_string();
    CString::new(s).unwrap().into_raw()
}

fn pdf_err_to_json(e: pdf_inspector::PdfError) -> *mut c_char {
    let code = match e {
        pdf_inspector::PdfError::Io(_) => "Io",
        pdf_inspector::PdfError::Parse(_) => "Parse",
        pdf_inspector::PdfError::Encrypted => "Encrypted",
        pdf_inspector::PdfError::InvalidStructure => "InvalidStructure",
        pdf_inspector::PdfError::NotAPdf(_) => "NotAPdf",
    };
    err_json(code, &e.to_string())
}

fn run_catching<F>(f: F) -> *mut c_char
where
    F: FnOnce() -> *mut c_char + panic::UnwindSafe,
{
    match panic::catch_unwind(f) {
        Ok(ptr) => ptr,
        Err(payload) => {
            let msg = if let Some(s) = payload.downcast_ref::<&str>() {
                s.to_string()
            } else if let Some(s) = payload.downcast_ref::<String>() {
                s.clone()
            } else {
                "unknown panic".to_string()
            };
            err_json("Panic", &msg)
        }
    }
}

// ---------------------------------------------------------------------------
// Serialization helpers
// ---------------------------------------------------------------------------

fn pdf_type_str(t: &pdf_inspector::PdfType) -> &'static str {
    match t {
        pdf_inspector::PdfType::TextBased => "TextBased",
        pdf_inspector::PdfType::Scanned => "Scanned",
        pdf_inspector::PdfType::ImageBased => "ImageBased",
        pdf_inspector::PdfType::Mixed => "Mixed",
    }
}

fn item_type_json(t: &pdf_inspector::types::ItemType) -> serde_json::Value {
    match t {
        pdf_inspector::types::ItemType::Text => serde_json::json!("Text"),
        pdf_inspector::types::ItemType::Image => serde_json::json!("Image"),
        pdf_inspector::types::ItemType::Link(url) => {
            serde_json::json!({"Link": url})
        }
        pdf_inspector::types::ItemType::FormField => serde_json::json!("FormField"),
    }
}

fn layout_json(l: &pdf_inspector::LayoutComplexity) -> serde_json::Value {
    serde_json::json!({
        "is_complex": l.is_complex,
        "pages_with_tables": l.pages_with_tables,
        "pages_with_columns": l.pages_with_columns,
    })
}

fn pdf_result_json(r: pdf_inspector::PdfProcessResult) -> serde_json::Value {
    let is_complex_layout = r.layout.is_complex;
    let pages_with_tables = r.layout.pages_with_tables.clone();
    let pages_with_columns = r.layout.pages_with_columns.clone();

    serde_json::json!({
        "pdf_type": pdf_type_str(&r.pdf_type),
        "markdown": r.markdown,
        "page_count": r.page_count,
        "processing_time_ms": r.processing_time_ms,
        "pages_needing_ocr": r.pages_needing_ocr,
        "title": r.title,
        "confidence": r.confidence,
        // Keep top-level layout fields for PHP DTO compatibility.
        "is_complex_layout": is_complex_layout,
        "pages_with_tables": pages_with_tables,
        "pages_with_columns": pages_with_columns,
        // Keep nested layout object for forward/backward compatibility.
        "layout": layout_json(&r.layout),
        "has_encoding_issues": r.has_encoding_issues,
    })
}

fn pdf_classification_json(c: pdf_inspector::PdfClassification) -> serde_json::Value {
    serde_json::json!({
        "pdf_type": pdf_type_str(&c.pdf_type),
        "page_count": c.page_count,
        "pages_needing_ocr": c.pages_needing_ocr,
        "confidence": c.confidence,
    })
}

fn text_items_json(items: Vec<pdf_inspector::types::TextItem>) -> serde_json::Value {
    let arr: Vec<serde_json::Value> = items
        .into_iter()
        .map(|item| {
            let (item_type, link_url) = match &item.item_type {
                pdf_inspector::types::ItemType::Link(url) => {
                    (serde_json::json!("Link"), Some(url.clone()))
                }
                other => (item_type_json(other), None),
            };
            serde_json::json!({
                "text": item.text,
                "x": item.x,
                "y": item.y,
                "width": item.width,
                "height": item.height,
                "font": item.font,
                "font_size": item.font_size,
                "page": item.page,
                "is_bold": item.is_bold,
                "is_italic": item.is_italic,
                "item_type": item_type,
                "link_url": link_url,
            })
        })
        .collect();
    serde_json::json!(arr)
}

fn page_region_results_json(results: Vec<pdf_inspector::PageRegionResult>) -> serde_json::Value {
    let arr: Vec<serde_json::Value> = results
        .into_iter()
        .map(|pr| {
            serde_json::json!({
                "page": pr.page,
                "regions": pr.regions.into_iter().map(|r| {
                    serde_json::json!({"text": r.text, "needs_ocr": r.needs_ocr})
                }).collect::<Vec<_>>(),
            })
        })
        .collect();
    serde_json::json!(arr)
}

fn pages_extraction_json(r: pdf_inspector::PagesExtractionResult) -> serde_json::Value {
    let pages: Vec<serde_json::Value> = r
        .pages
        .into_iter()
        .map(|p| {
            serde_json::json!({
                "page": p.page,
                "markdown": p.markdown,
                "needs_ocr": p.needs_ocr,
            })
        })
        .collect();
    serde_json::json!({
        "pages": pages,
        "pages_with_tables": r.pages_with_tables,
        "pages_with_columns": r.pages_with_columns,
        "pages_needing_ocr": r.pages_needing_ocr,
        "is_complex": r.is_complex,
    })
}

// ---------------------------------------------------------------------------
// Options parsing
// ---------------------------------------------------------------------------

fn parse_pages_json(pages_json: *const c_char) -> Option<Vec<u32>> {
    if pages_json.is_null() {
        return None;
    }
    let s = unsafe { CStr::from_ptr(pages_json).to_string_lossy() };
    if s.is_empty() || s == "null" {
        return None;
    }
    serde_json::from_str(&s).ok()
}

fn build_options(pages: Option<Vec<u32>>) -> pdf_inspector::PdfOptions {
    let mut opts = pdf_inspector::PdfOptions::new();
    if let Some(p) = pages {
        let set: HashSet<u32> = p.into_iter().collect();
        opts = pdf_inspector::PdfOptions {
            page_filter: Some(set),
            ..opts
        };
    }
    opts
}

// ---------------------------------------------------------------------------
// Public C ABI
// ---------------------------------------------------------------------------

/// Free a string previously returned by the FFI.
#[no_mangle]
pub extern "C" fn firepdf_free_string(ptr: *mut c_char) {
    if !ptr.is_null() {
        unsafe {
            let _ = CString::from_raw(ptr);
        }
    }
}

// -- process_pdf --

#[no_mangle]
pub extern "C" fn firepdf_process_pdf(path: *const c_char, pages_json: *const c_char) -> *mut c_char {
    run_catching(|| {
        let path_str = unsafe { CStr::from_ptr(path).to_string_lossy() };
        let pages = parse_pages_json(pages_json);
        let opts = build_options(pages);
        match pdf_inspector::process_pdf_with_options(&*path_str, opts) {
            Ok(r) => ok_json(pdf_result_json(r)),
            Err(e) => pdf_err_to_json(e),
        }
    })
}

#[no_mangle]
pub extern "C" fn firepdf_process_pdf_bytes(
    data_ptr: *const u8,
    data_len: usize,
    pages_json: *const c_char,
) -> *mut c_char {
    run_catching(|| {
        let data = unsafe { slice::from_raw_parts(data_ptr, data_len) };
        let pages = parse_pages_json(pages_json);
        let opts = build_options(pages);
        match pdf_inspector::process_pdf_mem_with_options(data, opts) {
            Ok(r) => ok_json(pdf_result_json(r)),
            Err(e) => pdf_err_to_json(e),
        }
    })
}

// -- detect_pdf --

#[no_mangle]
pub extern "C" fn firepdf_detect_pdf(path: *const c_char) -> *mut c_char {
    run_catching(|| {
        let path_str = unsafe { CStr::from_ptr(path).to_string_lossy() };
        match pdf_inspector::detect_pdf(&*path_str) {
            Ok(r) => ok_json(pdf_result_json(r)),
            Err(e) => pdf_err_to_json(e),
        }
    })
}

#[no_mangle]
pub extern "C" fn firepdf_detect_pdf_bytes(data_ptr: *const u8, data_len: usize) -> *mut c_char {
    run_catching(|| {
        let data = unsafe { slice::from_raw_parts(data_ptr, data_len) };
        match pdf_inspector::detect_pdf_mem(data) {
            Ok(r) => ok_json(pdf_result_json(r)),
            Err(e) => pdf_err_to_json(e),
        }
    })
}

// -- classify_pdf --

#[no_mangle]
pub extern "C" fn firepdf_classify_pdf(path: *const c_char) -> *mut c_char {
    run_catching(|| {
        let path_str = unsafe { CStr::from_ptr(path).to_string_lossy() };
        match std::fs::read(&*path_str) {
            Ok(bytes) => match pdf_inspector::classify_pdf_mem(&bytes) {
                Ok(c) => ok_json(pdf_classification_json(c)),
                Err(e) => pdf_err_to_json(e),
            },
            Err(e) => err_json("Io", &e.to_string()),
        }
    })
}

#[no_mangle]
pub extern "C" fn firepdf_classify_pdf_bytes(data_ptr: *const u8, data_len: usize) -> *mut c_char {
    run_catching(|| {
        let data = unsafe { slice::from_raw_parts(data_ptr, data_len) };
        match pdf_inspector::classify_pdf_mem(data) {
            Ok(c) => ok_json(pdf_classification_json(c)),
            Err(e) => pdf_err_to_json(e),
        }
    })
}

// -- extract_text --

#[no_mangle]
pub extern "C" fn firepdf_extract_text(path: *const c_char) -> *mut c_char {
    run_catching(|| {
        let path_str = unsafe { CStr::from_ptr(path).to_string_lossy() };
        match pdf_inspector::extract_text(&*path_str) {
            Ok(text) => ok_json(serde_json::json!(text)),
            Err(e) => pdf_err_to_json(e),
        }
    })
}

#[no_mangle]
pub extern "C" fn firepdf_extract_text_bytes(data_ptr: *const u8, data_len: usize) -> *mut c_char {
    run_catching(|| {
        let data = unsafe { slice::from_raw_parts(data_ptr, data_len) };
        match pdf_inspector::extractor::extract_text_mem(data) {
            Ok(text) => ok_json(serde_json::json!(text)),
            Err(e) => pdf_err_to_json(e),
        }
    })
}

// -- extract_text_with_positions --

#[no_mangle]
pub extern "C" fn firepdf_extract_text_with_positions(
    path: *const c_char,
    pages_json: *const c_char,
) -> *mut c_char {
    run_catching(|| {
        let path_str = unsafe { CStr::from_ptr(path).to_string_lossy() };
        let pages = parse_pages_json(pages_json);
        let result = if let Some(p) = pages {
            let set: HashSet<u32> = p.into_iter().collect();
            pdf_inspector::extract_text_with_positions_pages(&*path_str, Some(&set))
        } else {
            pdf_inspector::extract_text_with_positions(&*path_str)
        };
        match result {
            Ok(items) => ok_json(text_items_json(items)),
            Err(e) => pdf_err_to_json(e),
        }
    })
}

#[no_mangle]
pub extern "C" fn firepdf_extract_text_with_positions_bytes(
    data_ptr: *const u8,
    data_len: usize,
    pages_json: *const c_char,
) -> *mut c_char {
    run_catching(|| {
        let data = unsafe { slice::from_raw_parts(data_ptr, data_len) };
        let pages = parse_pages_json(pages_json);
        let result = if let Some(p) = pages {
            let set: HashSet<u32> = p.into_iter().collect();
            pdf_inspector::extractor::extract_text_with_positions_mem_pages(data, Some(&set))
        } else {
            pdf_inspector::extractor::extract_text_with_positions_mem(data)
        };
        match result {
            Ok(items) => ok_json(text_items_json(items)),
            Err(e) => pdf_err_to_json(e),
        }
    })
}

// -- extract_text_in_regions --

#[no_mangle]
pub extern "C" fn firepdf_extract_text_in_regions(
    path: *const c_char,
    regions_json: *const c_char,
) -> *mut c_char {
    run_catching(|| {
        let path_str = unsafe { CStr::from_ptr(path).to_string_lossy() };
        let regions = match parse_page_regions_json(regions_json) {
            Some(r) => r,
            None => return err_json("InvalidInput", "invalid regions_json"),
        };
        match std::fs::read(&*path_str) {
            Ok(bytes) => match pdf_inspector::extract_text_in_regions_mem(&bytes, &regions) {
                Ok(r) => ok_json(page_region_results_json(r)),
                Err(e) => pdf_err_to_json(e),
            },
            Err(e) => err_json("Io", &e.to_string()),
        }
    })
}

#[no_mangle]
pub extern "C" fn firepdf_extract_text_in_regions_bytes(
    data_ptr: *const u8,
    data_len: usize,
    regions_json: *const c_char,
) -> *mut c_char {
    run_catching(|| {
        let data = unsafe { slice::from_raw_parts(data_ptr, data_len) };
        let regions = match parse_page_regions_json(regions_json) {
            Some(r) => r,
            None => return err_json("InvalidInput", "invalid regions_json"),
        };
        match pdf_inspector::extract_text_in_regions_mem(data, &regions) {
            Ok(r) => ok_json(page_region_results_json(r)),
            Err(e) => pdf_err_to_json(e),
        }
    })
}

// -- extract_tables_in_regions --

#[no_mangle]
pub extern "C" fn firepdf_extract_tables_in_regions(
    path: *const c_char,
    regions_json: *const c_char,
) -> *mut c_char {
    run_catching(|| {
        let path_str = unsafe { CStr::from_ptr(path).to_string_lossy() };
        let regions = match parse_page_regions_json(regions_json) {
            Some(r) => r,
            None => return err_json("InvalidInput", "invalid regions_json"),
        };
        match std::fs::read(&*path_str) {
            Ok(bytes) => match pdf_inspector::extract_tables_in_regions_mem(&bytes, &regions) {
                Ok(r) => ok_json(page_region_results_json(r)),
                Err(e) => pdf_err_to_json(e),
            },
            Err(e) => err_json("Io", &e.to_string()),
        }
    })
}

#[no_mangle]
pub extern "C" fn firepdf_extract_tables_in_regions_bytes(
    data_ptr: *const u8,
    data_len: usize,
    regions_json: *const c_char,
) -> *mut c_char {
    run_catching(|| {
        let data = unsafe { slice::from_raw_parts(data_ptr, data_len) };
        let regions = match parse_page_regions_json(regions_json) {
            Some(r) => r,
            None => return err_json("InvalidInput", "invalid regions_json"),
        };
        match pdf_inspector::extract_tables_in_regions_mem(data, &regions) {
            Ok(r) => ok_json(page_region_results_json(r)),
            Err(e) => pdf_err_to_json(e),
        }
    })
}

// -- extract_pages_markdown --

#[no_mangle]
pub extern "C" fn firepdf_extract_pages_markdown(
    path: *const c_char,
    pages_json: *const c_char,
) -> *mut c_char {
    run_catching(|| {
        let path_str = unsafe { CStr::from_ptr(path).to_string_lossy() };
        let pages = parse_pages_json(pages_json);
        let pages_slice: Option<&[u32]> = pages.as_deref();
        match std::fs::read(&*path_str) {
            Ok(bytes) => {
                match pdf_inspector::extract_pages_markdown_mem(&bytes, pages_slice) {
                    Ok(r) => ok_json(pages_extraction_json(r)),
                    Err(e) => pdf_err_to_json(e),
                }
            }
            Err(e) => err_json("Io", &e.to_string()),
        }
    })
}

#[no_mangle]
pub extern "C" fn firepdf_extract_pages_markdown_bytes(
    data_ptr: *const u8,
    data_len: usize,
    pages_json: *const c_char,
) -> *mut c_char {
    run_catching(|| {
        let data = unsafe { slice::from_raw_parts(data_ptr, data_len) };
        let pages = parse_pages_json(pages_json);
        let pages_slice: Option<&[u32]> = pages.as_deref();
        match pdf_inspector::extract_pages_markdown_mem(data, pages_slice) {
            Ok(r) => ok_json(pages_extraction_json(r)),
            Err(e) => pdf_err_to_json(e),
        }
    })
}

// ---------------------------------------------------------------------------
// Region parsing helpers
// ---------------------------------------------------------------------------

fn parse_page_regions_json(regions_json: *const c_char) -> Option<Vec<(u32, Vec<[f32; 4]>)>> {
    if regions_json.is_null() {
        return None;
    }
    let s = unsafe { CStr::from_ptr(regions_json).to_string_lossy() };
    if s.is_empty() || s == "null" {
        return None;
    }
    let raw: Vec<(u32, Vec<Vec<f32>>)> = serde_json::from_str(&s).ok()?;
    let mut out = Vec::with_capacity(raw.len());
    for (page, rects) in raw {
        let mut page_rects = Vec::with_capacity(rects.len());
        for r in rects {
            if r.len() == 4 {
                page_rects.push([r[0], r[1], r[2], r[3]]);
            } else {
                return None;
            }
        }
        out.push((page, page_rects));
    }
    Some(out)
}
