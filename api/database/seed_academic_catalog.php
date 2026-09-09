<?php
/**
 * SHULE CAFE Enterprise
 * Academic Catalog Seeder (Nursery, Primary, O-Level, A-Level)
 * Single Source of Truth for National Curriculum Standards
 */

require_once __DIR__ . '/../config/db.php';

try {
    $conn->beginTransaction();

    function genUuid() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    $stmtUpsert = $conn->prepare("
        INSERT INTO academic_templates (id, type, name, code, level_code, description, details, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE 
            name = VALUES(name),
            description = VALUES(description),
            details = COALESCE(VALUES(details), details),
            status = 'active'
    ");

    // 1. EDUCATION TIERS (LEVELS)
    $levels = [
        [
            'id' => 'tpl-lvl-nursery',
            'name' => 'Early Childhood & Nursery',
            'code' => 'NURSERY',
            'level_code' => 'NURSERY',
            'description' => 'Pre-primary foundation learning and early child development',
            'details' => json_encode([
                'abbr' => 'NUR',
                'curriculum_type' => 'Foundation Play & Early Literacy',
                'cycle_years' => 3
            ])
        ],
        [
            'id' => 'tpl-lvl-primary',
            'name' => 'Primary Education',
            'code' => 'PRIM',
            'level_code' => 'PRIM',
            'description' => 'Seven-year national primary curriculum across all standard grades (Std 1 - 7)',
            'details' => json_encode([
                'abbr' => 'PRIM',
                'curriculum_type' => 'National Primary Education (Std 1-7)',
                'cycle_years' => 7
            ])
        ],
        [
            'id' => 'tpl-lvl-o',
            'name' => 'Ordinary Level Secondary (O-Level)',
            'code' => 'O-LEVEL',
            'level_code' => 'O-LEVEL',
            'description' => 'Four-year ordinary secondary national curriculum (Forms 1 - 4)',
            'details' => json_encode([
                'abbr' => 'O-LVL',
                'curriculum_type' => 'NECTA O-Level Secondary (CSEE)',
                'cycle_years' => 4
            ])
        ],
        [
            'id' => 'tpl-lvl-a',
            'name' => 'Advanced Level High School (A-Level)',
            'code' => 'A-LEVEL',
            'level_code' => 'A-LEVEL',
            'description' => 'Two-year advanced secondary specialization streams (Forms 5 - 6)',
            'details' => json_encode([
                'abbr' => 'A-LVL',
                'curriculum_type' => 'NECTA A-Level Advanced (ACSEE)',
                'cycle_years' => 2
            ])
        ]
    ];

    foreach ($levels as $l) {
        $stmtUpsert->execute([$l['id'], 'level', $l['name'], $l['code'], $l['level_code'], $l['description'], $l['details']]);
    }

    // 2. STANDARD CLASS TIERS (NURSERY & PRIMARY)
    $classes = [
        // Nursery Classes
        ['id' => 'tpl-cls-nur-1', 'name' => 'Baby Class', 'code' => 'BABY', 'level_code' => 'NURSERY', 'description' => 'First stage of early childhood education'],
        ['id' => 'tpl-cls-nur-2', 'name' => 'Middle Class', 'code' => 'MIDDLE', 'level_code' => 'NURSERY', 'description' => 'Intermediate early childhood stage'],
        ['id' => 'tpl-cls-nur-3', 'name' => 'Pre-Unit', 'code' => 'PRE-UNIT', 'level_code' => 'NURSERY', 'description' => 'Pre-primary transition year before Standard 1'],

        // Primary Classes
        ['id' => 'tpl-cls-std-1', 'name' => 'Standard 1', 'code' => 'STD1', 'level_code' => 'PRIM', 'description' => 'Primary school entry grade (Std 1)'],
        ['id' => 'tpl-cls-std-2', 'name' => 'Standard 2', 'code' => 'STD2', 'level_code' => 'PRIM', 'description' => 'Primary school grade 2 (Std 2)'],
        ['id' => 'tpl-cls-std-3', 'name' => 'Standard 3', 'code' => 'STD3', 'level_code' => 'PRIM', 'description' => 'Primary school grade 3 (Std 3)'],
        ['id' => 'tpl-cls-std-4', 'name' => 'Standard 4', 'code' => 'STD4', 'level_code' => 'PRIM', 'description' => 'Primary school middle grade (Std 4 national assessment)'],
        ['id' => 'tpl-cls-std-5', 'name' => 'Standard 5', 'code' => 'STD5', 'level_code' => 'PRIM', 'description' => 'Primary school upper grade 5 (Std 5)'],
        ['id' => 'tpl-cls-std-6', 'name' => 'Standard 6', 'code' => 'STD6', 'level_code' => 'PRIM', 'description' => 'Primary school grade 6 (Std 6)'],
        ['id' => 'tpl-cls-std-7', 'name' => 'Standard 7', 'code' => 'STD7', 'level_code' => 'PRIM', 'description' => 'Primary school final graduation year (Std 7 PSLE)']
    ];

    foreach ($classes as $c) {
        $stmtUpsert->execute([$c['id'], 'class', $c['name'], $c['code'], $c['level_code'], $c['description'], null]);
    }

    // 3. MASTER APPROVED SUBJECTS (NURSERY & PRIMARY)
    $subjects = [
        // Nursery Subjects
        ['id' => 'tpl-sbj-nur-1', 'name' => 'Developing Language & Communication', 'code' => 'NUR-LANG', 'level_code' => 'NURSERY', 'description' => 'Early listening, speaking, and vocabulary development'],
        ['id' => 'tpl-sbj-nur-2', 'name' => 'Early Numeracy & Mathematics', 'code' => 'NUR-NUM', 'level_code' => 'NURSERY', 'description' => 'Basic number concept, counting, and pattern recognition'],
        ['id' => 'tpl-sbj-nur-3', 'name' => 'Environmental Care & Health', 'code' => 'NUR-ENV', 'level_code' => 'NURSERY', 'description' => 'Personal hygiene, environmental awareness, and safety'],
        ['id' => 'tpl-sbj-nur-4', 'name' => 'Creative Arts & Games', 'code' => 'NUR-ART', 'level_code' => 'NURSERY', 'description' => 'Drawing, coloring, music, and motor skill activities'],
        ['id' => 'tpl-sbj-nur-5', 'name' => 'Social & Emotional Skills', 'code' => 'NUR-SOC', 'level_code' => 'NURSERY', 'description' => 'Relational habits, sharing, and self-expression'],

        // Primary Subjects (National Standard Curriculum)
        ['id' => 'tpl-sbj-prim-1', 'name' => 'Basic Mathematics', 'code' => 'STD-MATH', 'level_code' => 'PRIM', 'description' => 'Core primary arithmetic and problem solving'],
        ['id' => 'tpl-sbj-prim-2', 'name' => 'English Language', 'code' => 'STD-ENG', 'level_code' => 'PRIM', 'description' => 'Primary English grammar, reading comprehension, and composition'],
        ['id' => 'tpl-sbj-prim-3', 'name' => 'Kiswahili', 'code' => 'STD-KISW', 'level_code' => 'PRIM', 'description' => 'Sarufi, usomaji, insha na fasihi ya lugha ya taifa'],
        ['id' => 'tpl-sbj-prim-4', 'name' => 'Science and Technology', 'code' => 'STD-SCI', 'level_code' => 'PRIM', 'description' => 'Primary natural science, experiment basics, and technology'],
        ['id' => 'tpl-sbj-prim-5', 'name' => 'Social Studies', 'code' => 'STD-SOC', 'level_code' => 'PRIM', 'description' => 'Geography, history, and community studies (Maarifa ya Jamii)'],
        ['id' => 'tpl-sbj-prim-6', 'name' => 'Civic and Moral Education', 'code' => 'STD-CIV', 'level_code' => 'PRIM', 'description' => 'Ethics, citizenship, human rights, and national values'],
        ['id' => 'tpl-sbj-prim-7', 'name' => 'Vocational Skills', 'code' => 'STD-VOC', 'level_code' => 'PRIM', 'description' => 'Practical work, agriculture, and life skills (Stadi za Kazi)']
    ];

    foreach ($subjects as $s) {
        $stmtUpsert->execute([$s['id'], 'subject', $s['name'], $s['code'], $s['level_code'], $s['description'], null]);
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Academic Catalog successfully seeded for Nursery, Primary, O-Level, and A-Level.'
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Seeder failed: ' . $e->getMessage()
    ]);
}
