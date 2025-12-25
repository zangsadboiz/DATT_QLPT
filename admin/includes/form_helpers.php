<?php
/**
 * Form Helper Functions
 * Quản lý phòng trọ - Consistent Form Elements
 */

/**
 * Render text input field
 */
function render_input($name, $label, $value = '', $placeholder = '', $required = false, $type = 'text', $attributes = []) {
    $req_mark = $required ? ' required' : '';
    $req_class = $required ? ' class="required"' : '';
    $id = $attributes['id'] ?? $name;
    
    $attr_str = '';
    foreach ($attributes as $key => $val) {
        if ($key !== 'id') {
            $attr_str .= sprintf(' %s="%s"', htmlspecialchars($key), htmlspecialchars($val));
        }
    }
    
    $html = sprintf(
        '<div class="mb-3">
            <label for="%s" class="form-label"%s>%s</label>
            <input type="%s" class="form-control" id="%s" name="%s" value="%s" placeholder="%s"%s%s>
        </div>',
        htmlspecialchars($id),
        $req_class,
        htmlspecialchars($label),
        htmlspecialchars($type),
        htmlspecialchars($id),
        htmlspecialchars($name),
        htmlspecialchars($value),
        htmlspecialchars($placeholder),
        $req_mark,
        $attr_str
    );
    
    return $html;
}

/**
 * Render textarea field
 */
function render_textarea($name, $label, $value = '', $placeholder = '', $required = false, $rows = 4, $attributes = []) {
    $req_mark = $required ? ' required' : '';
    $req_class = $required ? ' class="required"' : '';
    $id = $attributes['id'] ?? $name;
    
    $attr_str = '';
    foreach ($attributes as $key => $val) {
        if ($key !== 'id') {
            $attr_str .= sprintf(' %s="%s"', htmlspecialchars($key), htmlspecialchars($val));
        }
    }
    
    $html = sprintf(
        '<div class="mb-3">
            <label for="%s" class="form-label"%s>%s</label>
            <textarea class="form-control" id="%s" name="%s" rows="%d" placeholder="%s"%s%s>%s</textarea>
        </div>',
        htmlspecialchars($id),
        $req_class,
        htmlspecialchars($label),
        htmlspecialchars($id),
        htmlspecialchars($name),
        (int)$rows,
        htmlspecialchars($placeholder),
        $req_mark,
        $attr_str,
        htmlspecialchars($value)
    );
    
    return $html;
}

/**
 * Render select dropdown
 */
function render_select($name, $label, $options, $selected = '', $required = false, $attributes = []) {
    $req_mark = $required ? ' required' : '';
    $req_class = $required ? ' class="required"' : '';
    $id = $attributes['id'] ?? $name;
    
    $attr_str = '';
    foreach ($attributes as $key => $val) {
        if ($key !== 'id') {
            $attr_str .= sprintf(' %s="%s"', htmlspecialchars($key), htmlspecialchars($val));
        }
    }
    
    $options_html = '';
    foreach ($options as $value => $text) {
        $selected_attr = ($value == $selected) ? ' selected' : '';
        $options_html .= sprintf(
            '<option value="%s"%s>%s</option>',
            htmlspecialchars($value),
            $selected_attr,
            htmlspecialchars($text)
        );
    }
    
    $html = sprintf(
        '<div class="mb-3">
            <label for="%s" class="form-label"%s>%s</label>
            <select class="form-select" id="%s" name="%s"%s%s>
                %s
            </select>
        </div>',
        htmlspecialchars($id),
        $req_class,
        htmlspecialchars($label),
        htmlspecialchars($id),
        htmlspecialchars($name),
        $req_mark,
        $attr_str,
        $options_html
    );
    
    return $html;
}

/**
 * Render file input
 */
function render_file_input($name, $label, $required = false, $accept = 'image/*', $preview_id = null) {
    $req_mark = $required ? ' required' : '';
    $req_class = $required ? ' class="required"' : '';
    $preview_attr = $preview_id ? sprintf(' data-preview="%s"', htmlspecialchars($preview_id)) : '';
    
    $html = sprintf(
        '<div class="mb-3">
            <label for="%s" class="form-label"%s>%s</label>
            <input type="file" class="form-control" id="%s" name="%s" accept="%s"%s%s>
        </div>',
        htmlspecialchars($name),
        $req_class,
        htmlspecialchars($label),
        htmlspecialchars($name),
        htmlspecialchars($name),
        htmlspecialchars($accept),
        $req_mark,
        $preview_attr
    );
    
    return $html;
}

/**
 * Render checkbox
 */
function render_checkbox($name, $label, $checked = false, $value = '1') {
    $checked_attr = $checked ? ' checked' : '';
    
    $html = sprintf(
        '<div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="%s" name="%s" value="%s"%s>
            <label class="form-check-label" for="%s">%s</label>
        </div>',
        htmlspecialchars($name),
        htmlspecialchars($name),
        htmlspecialchars($value),
        $checked_attr,
        htmlspecialchars($name),
        htmlspecialchars($label)
    );
    
    return $html;
}

/**
 * Render submit button
 */
function render_submit($label = 'Lưu', $class = 'btn-primary', $icon = 'save') {
    $html = sprintf(
        '<button type="submit" class="btn %s">
            <i class="bi bi-%s me-1"></i> %s
        </button>',
        htmlspecialchars($class),
        htmlspecialchars($icon),
        htmlspecialchars($label)
    );
    
    return $html;
}

/**
 * Render cancel/back button
 */
function render_back_button($url, $label = 'Quay lại') {
    $html = sprintf(
        '<a href="%s" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> %s
        </a>',
        htmlspecialchars($url),
        htmlspecialchars($label)
    );
    
    return $html;
}

/**
 * Render form actions (submit + back)
 */
function render_form_actions($back_url, $submit_label = 'Lưu', $back_label = 'Quay lại') {
    $html = sprintf(
        '<div class="mb-3 d-flex gap-2">
            %s
            %s
        </div>',
        render_submit($submit_label),
        render_back_button($back_url, $back_label)
    );
    
    return $html;
}

/**
 * Sanitize and validate input
 */
function sanitize_input($input, $type = 'text') {
    $input = trim($input);
    
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT) !== false ? (int)$input : 0;
        
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT) !== false ? (float)$input : 0.0;
        
        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL) !== false ? $input : '';
        
        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL) !== false ? $input : '';
        
        case 'html':
            // Allow HTML but sanitize
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        case 'text':
        default:
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}
