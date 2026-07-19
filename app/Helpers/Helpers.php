<?php

namespace App\Helpers;

class Helpers
{
    public static function formatRupiah($angka): string
    {
        return "Rp " . number_format((float)$angka, 0, ',', '.');
    }

    public static function getInitials(string $name): string
    {
        $words = explode(" ", $name);
        $initials = "";
        foreach ($words as $w) {
            if (!empty($w)) {
                $initials .= strtoupper($w[0]);
            }
        }
        return substr($initials, 0, 2);
    }

    public static function timeAgo($datetime, bool $full = false): string
    {
        $now = new \DateTime();
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = [
            'y' => 'tahun', 'm' => 'bulan', 'w' => 'minggu',
            'd' => 'hari', 'h' => 'jam', 'i' => 'menit', 's' => 'detik',
        ];
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v;
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' yang lalu' : 'baru saja';
    }

    public static function renderPagination(int $currentPage, int $totalPages, array $queryParams = []): string
    {
        $totalPages = max(1, $totalPages);
        $html = '<div class="pagination-wrapper" style="display: flex; justify-content: center; gap: 0.5rem; align-items: center; padding: 1.5rem 1rem;">';

        if ($currentPage > 1) {
            $qs = $queryParams; $qs['page'] = $currentPage - 1;
            $html .= '<a href="?' . http_build_query($qs) . '" class="btn btn-outline btn-sm"><i class="fa-solid fa-chevron-left"></i></a>';
        } else {
            $html .= '<button class="btn btn-outline btn-sm" disabled style="opacity:0.5;cursor:not-allowed;"><i class="fa-solid fa-chevron-left"></i></button>';
        }

        for ($i = 1; $i <= $totalPages; $i++) {
            if ($totalPages > 7) {
                if ($i == 1 || $i == $totalPages || ($i >= $currentPage - 1 && $i <= $currentPage + 1)) {
                    $html .= self::pageBtn($i, $currentPage, $queryParams);
                } elseif ($i == $currentPage - 2 || $i == $currentPage + 2) {
                    $html .= '<span style="padding:0.5rem;color:var(--muted-foreground);">...</span>';
                }
            } else {
                $html .= self::pageBtn($i, $currentPage, $queryParams);
            }
        }

        if ($currentPage < $totalPages) {
            $qs = $queryParams; $qs['page'] = $currentPage + 1;
            $html .= '<a href="?' . http_build_query($qs) . '" class="btn btn-outline btn-sm"><i class="fa-solid fa-chevron-right"></i></a>';
        } else {
            $html .= '<button class="btn btn-outline btn-sm" disabled style="opacity:0.5;cursor:not-allowed;"><i class="fa-solid fa-chevron-right"></i></button>';
        }

        $html .= '</div>';
        return $html;
    }

    private static function pageBtn(int $i, int $current, array $queryParams): string
    {
        if ($i === $current) {
            return '<span class="btn btn-primary btn-sm" style="pointer-events:none;">' . $i . '</span>';
        }
        $qs = $queryParams; $qs['page'] = $i;
        return '<a href="?' . http_build_query($qs) . '" class="btn btn-outline btn-sm">' . $i . '</a>';
    }
}
