<?php
if (!defined('HIIFI')) { exit; }

function card_design($type) {
    $staff = ($type === 'staff');
    $d = [
        'type' => $type,
        'school_name' => get_setting('school_name', 'LAPS School & College'),
        'school_addr' => get_setting('school_address', ''),
        'logo' => get_setting('school_logo', ''),
        'theme' => $staff ? '#0b5a43' : '#0067d7',
        'accent' => '#f2d500',
        'top_text' => '#ffffff',
        'name_color' => $staff ? '#0b5a43' : '#0067d7',
        'role_color' => '#083a2b',
        'school_font' => $staff ? 16 : 14,
        'name_font' => 15,
        'role_text' => $staff ? 'STAFF MEMBER' : 'STUDENT',
        'id_label' => 'Employee ID',
        'designation_label' => 'Designation',
        'department_label' => 'Department',
        'father_label' => 'Father Name',
        'class_label' => 'Class',
        'gr_label' => 'GR No',
        'dob_label' => 'DOB',
        'cell_label' => 'Cell No',
        'valid_label' => 'Valid Till:',
        'principal_text' => 'Principal',
        'show_school' => 'YES',
        'show_addr' => 'YES',
        'show_qr' => 'YES',
        'show_sign' => 'YES',
        'show_emp' => 'YES',
        'show_desig' => 'YES',
        'show_dept' => 'YES',
        'show_father' => 'YES',
        'show_class' => 'YES',
        'show_gr' => 'YES',
        'show_dob' => 'YES',
        'show_cell' => 'YES',
    ];
    $saved = get_setting('card_design_' . $type, '');
    if ($saved !== '') {
        $j = json_decode($saved, true);
        if (is_array($j)) { $d = array_merge($d, $j); }
    }
    $d['school_name'] = trim((string) $d['school_name']);
    $d['school_addr'] = trim((string) $d['school_addr']);
    $d['logo'] = trim((string) $d['logo']);
    foreach (['theme', 'accent', 'top_text', 'name_color', 'role_color'] as $ck) {
        if (!preg_match('/^#?[0-9a-fA-F]{6}$/', (string) $d[$ck])) { unset($d[$ck]); }
    }
    return $d + ['theme' => $staff ? '#0b5a43' : '#0067d7', 'accent' => '#f2d500', 'top_text' => '#ffffff', 'name_color' => $staff ? '#0b5a43' : '#0067d7', 'role_color' => '#083a2b'];
}

function card_design_save($type, $p, $file = null) {
    $d = card_design($type);

    $txt = function ($k, $max = 160) use ($p) {
        $v = isset($p[$k]) ? trim((string) $p[$k]) : '';
        $v = preg_replace('/[\r\n\t]+/', ' ', $v);
        return mb_substr($v, 0, $max);
    };
    $col = function ($k, $def) use ($p) {
        $v = trim((string) ($p[$k] ?? ''));
        return preg_match('/^#?[0-9a-fA-F]{6}$/', $v) ? '#' . strtolower(ltrim($v, '#')) : $def;
    };
    $yn = function ($k) use ($p) { return (($p[$k] ?? 'YES') === 'NO') ? 'NO' : 'YES'; };
    $num = function ($k, $lo, $hi, $def) use ($p) {
        $v = (int) ($p[$k] ?? $def);
        if ($v < $lo) { $v = $lo; }
        if ($v > $hi) { $v = $hi; }
        return $v;
    };

    $d['school_name'] = $txt('school_name', 80);
    $d['school_addr'] = $txt('school_addr', 120);
    $d['theme'] = $col('theme', $d['theme']);
    $d['accent'] = $col('accent', $d['accent']);
    $d['top_text'] = $col('top_text', $d['top_text']);
    $d['name_color'] = $col('name_color', $d['name_color']);
    $d['role_color'] = $col('role_color', $d['role_color']);
    $d['school_font'] = $num('school_font', 8, 30, $d['school_font']);
    $d['name_font'] = $num('name_font', 8, 26, $d['name_font']);
    $d['role_text'] = $txt('role_text', 30);
    $d['valid_label'] = $txt('valid_label', 30);
    $d['principal_text'] = $txt('principal_text', 30);
    foreach (['show_school', 'show_addr', 'show_qr', 'show_sign', 'show_emp', 'show_desig', 'show_dept', 'show_father', 'show_class', 'show_gr', 'show_dob', 'show_cell'] as $k) {
        if (isset($p[$k])) { $d[$k] = $yn($k); }
    }
    foreach (['id_label', 'designation_label', 'department_label', 'father_label', 'class_label', 'gr_label', 'dob_label', 'cell_label'] as $k) {
        if (isset($p[$k])) { $d[$k] = $txt($k, 30); }
    }

    if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && (int) ($file['size'] ?? 0) <= 5242880) {
        $info = @getimagesize($file['tmp_name']);
        if ($info !== false) {
            $map = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
            $ext = $map[$info[2]] ?? 'png';
            $dir = dirname(__DIR__) . '/uploads/design';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $name = 'logo_' . $type . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            if (@move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
                foreach ((array) glob($dir . '/logo_' . $type . '_*') as $old) {
                    if (basename($old) !== $name) { @unlink($old); }
                }
                $d['logo'] = 'uploads/design/' . $name;
            }
        }
    }

    set_setting('card_design_' . $type, json_encode($d));
    return $d;
}
