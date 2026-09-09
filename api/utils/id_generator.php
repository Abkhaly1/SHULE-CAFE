<?php
/**
 * SHULE CAFE Enterprise
 * Copyright (c) 2026 SHULE CAFE Enterprise. All Rights Reserved.
 * PROPRIETARY & CONFIDENTIAL. Unauthorized copying or redistribution is strictly prohibited.
 *
 * Universal Identification Engine (Option A Standard)
 * Formats:
 * - Teachers:         SC/{YEAR}-{SCH_SEQ}/TCH-{SEQ}   (e.g. SC/2026-0001/TCH-001)
 * - Students:         SC/{YEAR}-{SCH_SEQ}/STD-{SEQ}   (e.g. SC/2026-0001/STD-0001)
 * - Parents:          SC/{YEAR}-{SCH_SEQ}/PAR-{SEQ}   (e.g. SC/2026-0001/PAR-0001)
 * - School Admins:    SC/{YEAR}-{SCH_SEQ}/ADM-{SEQ}   (e.g. SC/2026-0001/ADM-001)
 * - Regional Officer: SC/REG-{REGION}/OFF-{SEQ}       (e.g. SC/REG-MM/OFF-001)
 */

if (!function_exists('getRegionCode')) {
    /**
     * Maps administrative region names to standard 2-3 letter codes
     */
    function getRegionCode(?string $regionName): string {
        if (empty($regionName)) {
            return 'TZ';
        }

        $clean = mb_strtoupper(trim($regionName), 'UTF-8');

        $regionMap = [
            'MJINI MAGHARIBI'       => 'MM',
            'MAGHARIBI'             => 'MM',
            'MJINI'                 => 'MM',
            'DAR ES SALAAM'         => 'DAR',
            'DSM'                   => 'DAR',
            'ARUSHA'                => 'ARU',
            'DODOMA'                => 'DOD',
            'MWANZA'                => 'MWZ',
            'KILIMANJARO'           => 'KIL',
            'MOSHI'                 => 'KIL',
            'MBEYA'                 => 'MBY',
            'MOROGORO'              => 'MOR',
            'TANGA'                 => 'TAN',
            'TABORA'                => 'TAB',
            'KIGOMA'                => 'KIG',
            'SHINYANGA'             => 'SHY',
            'KAGERA'                => 'KAG',
            'MARA'                  => 'MAR',
            'MANYARA'               => 'MAN',
            'SINGIDA'               => 'SNG',
            'RUKWA'                 => 'RKW',
            'RUVUMA'                => 'RUV',
            'IRINGA'                => 'IRI',
            'MTWARA'                => 'MTW',
            'LINDI'                 => 'LND',
            'PWANI'                 => 'PWN',
            'COAST'                 => 'PWN',
            'GEITA'                 => 'GEI',
            'SIMIYU'                => 'SMY',
            'NJOMBE'                => 'NJO',
            'KATAVI'                => 'KAT',
            'SONGWE'                => 'SGW',
            'KASKAZINI UNGUJA'      => 'KU',
            'KUSINI UNGUJA'         => 'SU',
            'KASKAZINI PEMBA'       => 'KP',
            'KUSINI PEMBA'          => 'SP'
        ];

        foreach ($regionMap as $name => $code) {
            if (str_contains($clean, $name)) {
                return $code;
            }
        }

        // Fallback: take up to 3 uppercase alphanumeric letters
        $alphanumeric = preg_replace('/[^A-Z]/', '', $clean);
        return !empty($alphanumeric) ? substr($alphanumeric, 0, 3) : 'TZ';
    }
}

if (!function_exists('getSchoolCoreNumber')) {
    /**
     * Resolves the school's unique 8-character core number (e.g. 2026-0001)
     */
    function getSchoolCoreNumber(PDO $conn, ?string $schoolId): string {
        $defaultCore = date('Y') . '-0001';
        if (empty($schoolId)) {
            return $defaultCore;
        }

        try {
            $stmt = $conn->prepare("SELECT school_code FROM schools WHERE id = ? LIMIT 1");
            $stmt->execute([$schoolId]);
            $schoolCode = $stmt->fetchColumn();

            if (!empty($schoolCode)) {
                // Match pattern like 2026-0001 or 26-0001 from S/CAFE-2026-0001
                if (preg_match('/(\d{4}-\d{4})/', $schoolCode, $matches)) {
                    return $matches[1];
                }
                if (preg_match('/(\d{2}-\d{4})/', $schoolCode, $matches)) {
                    return '20' . $matches[1];
                }
                // Strip S/CAFE- if present
                $stripped = preg_replace('/^S\/CAFE-?/i', '', trim($schoolCode));
                if (!empty($stripped)) {
                    return $stripped;
                }
            }
        } catch (Throwable $e) {
            // Log or fallback
        }

        return $defaultCore;
    }
}

if (!function_exists('generateShuleCafeUserId')) {
    /**
     * Generates a collision-free Option A Systematic User Identification Code
     *
     * @param PDO $conn Database connection
     * @param string|null $schoolId UUID of the school (null for regional officers)
     * @param string $role Role: teacher, student, parent, regional_officer, tenant_admin, headmaster
     * @param string|null $region Region name or code (used for regional_officer)
     * @return string Formatted Systematic User ID
     */
    function generateShuleCafeUserId(PDO $conn, ?string $schoolId, string $role, ?string $region = null, array $excludeCodes = []): string {
        $roleClean = strtolower(trim($role));

        // 1. Regional Officer Generation: SC/REG-{REGION}/OFF-{SEQ}
        if ($roleClean === 'regional_officer') {
            $regCode = getRegionCode($region);
            $prefix = "SC/REG-{$regCode}/OFF-";

            $stmt = $conn->prepare("
                SELECT user_code 
                FROM users 
                WHERE role = 'regional_officer' AND user_code LIKE ?
            ");
            $stmt->execute([$prefix . '%']);
            $codes = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $maxSeq = 0;
            foreach ($codes as $code) {
                if (preg_match('/OFF-(\d+)$/', $code, $m)) {
                    $seq = (int)$m[1];
                    if ($seq > $maxSeq) {
                        $maxSeq = $seq;
                    }
                }
            }

            $nextSeq = $maxSeq + 1;

            // Collision check loop against DB and batch
            do {
                $generatedCode = sprintf("SC/REG-%s/OFF-%03d", $regCode, $nextSeq);
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_code = ?");
                $checkStmt->execute([$generatedCode]);
                $existsInDb = ((int)$checkStmt->fetchColumn()) > 0;
                $existsInBatch = in_array($generatedCode, $excludeCodes, true);
                if ($existsInDb || $existsInBatch) {
                    $nextSeq++;
                }
            } while ($existsInDb || $existsInBatch);

            return $generatedCode;
        }

        // 2. School-Bound User Generation
        $schoolCore = getSchoolCoreNumber($conn, $schoolId);

        $roleTag = 'USR';
        $padLength = 3;

        switch ($roleClean) {
            case 'teacher':
                $roleTag = 'TCH';
                $padLength = 3;
                break;
            case 'student':
                $roleTag = 'STD';
                $padLength = 4;
                break;
            case 'parent':
            case 'guardian':
                $roleTag = 'PAR';
                $padLength = 4;
                break;
            case 'tenant_admin':
            case 'school_admin':
            case 'headmaster':
                $roleTag = 'ADM';
                $padLength = 3;
                break;
            default:
                $roleTag = 'USR';
                $padLength = 3;
                break;
        }

        $prefix = "SC/{$schoolCore}/{$roleTag}-";

        // Query all existing codes with this prefix for this school
        $stmt = $conn->prepare("
            SELECT user_code 
            FROM users 
            WHERE school_id = ? AND user_code LIKE ?
        ");
        $stmt->execute([$schoolId, $prefix . '%']);
        $existingCodes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $maxSeq = 0;
        foreach ($existingCodes as $c) {
            if (preg_match('/' . preg_quote($roleTag, '/') . '-(\d+)$/', $c, $m)) {
                $val = (int)$m[1];
                if ($val > $maxSeq) {
                    $maxSeq = $val;
                }
            }
        }

        // If no records match the new pattern, check count of that role in school as baseline
        if ($maxSeq === 0) {
            $cntStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE school_id = ? AND role = ?");
            $cntStmt->execute([$schoolId, $roleClean]);
            $count = (int)$cntStmt->fetchColumn();
            // Start at count + 1 or at least 1
            $nextSeq = max(1, $count + 1);
        } else {
            $nextSeq = $maxSeq + 1;
        }

        // Absolute Zero-Collision safety check against DB and batch
        do {
            $generatedCode = sprintf("SC/%s/%s-%0" . $padLength . "d", $schoolCore, $roleTag, $nextSeq);
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE user_code = ?");
            $checkStmt->execute([$generatedCode]);
            $existsInDb = ((int)$checkStmt->fetchColumn()) > 0;
            $existsInBatch = in_array($generatedCode, $excludeCodes, true);
            if ($existsInDb || $existsInBatch) {
                $nextSeq++;
            }
        } while ($existsInDb || $existsInBatch);

        return $generatedCode;
    }
}
