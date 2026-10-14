<?php

declare(strict_types=1);

namespace App\Modules\ImporRba\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

/**
 * Membaca satu sheet sebagai larik nilai (bukan rumus). File RBA bisa ±22 MB dengan puluhan sheet,
 * jadi hanya sheet terpilih yang dimuat, tanpa gaya, dan dibatasi kolom/baris.
 * Untuk sel rumus dipakai nilai tersimpan terakhir (tidak menghitung ulang rumus yang mungkin #REF!).
 */
final class PembacaXlsx
{
    public const MAKS_KOLOM = 80;

    public const MAKS_BARIS = 20000;

    /** @return list<string> */
    public function daftarSheet(string $path): array
    {
        return IOFactory::createReaderForFile($path)->listWorksheetNames($path);
    }

    /** @return list<list<mixed>> */
    public function baca(string $path, string $sheet): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([$sheet]);
        }
        $reader->setReadFilter(new class implements IReadFilter
        {
            // Parameter tanpa tipe agar kompatibel dengan PhpSpreadsheet 1.x maupun 2.x+.
            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= PembacaXlsx::MAKS_BARIS
                    && Coordinate::columnIndexFromString($columnAddress) <= PembacaXlsx::MAKS_KOLOM;
            }
        });

        $buku = $reader->load($path);
        $lembar = $buku->getSheetByName($sheet) ?? $buku->getSheet(0);
        $hasil = [];
        foreach ($lembar->getRowIterator() as $baris) {
            $i = $baris->getRowIndex() - 1;
            $isi = [];
            $iterator = $baris->getCellIterator();
            $iterator->setIterateOnlyExistingCells(true);
            foreach ($iterator as $sel) {
                $j = Coordinate::columnIndexFromString($sel->getColumn()) - 1;
                $nilai = $sel->getDataType() === DataType::TYPE_FORMULA ? $sel->getOldCalculatedValue() : $sel->getValue();
                if ($nilai instanceof RichText) {
                    $nilai = $nilai->getPlainText();
                }
                if ($nilai !== null && $nilai !== '') {
                    $isi[$j] = $nilai;
                }
            }
            if ($isi !== []) {
                $maks = max(array_keys($isi));
                $hasil[$i] = array_replace(array_fill(0, $maks + 1, null), $isi);
            }
        }
        $buku->disconnectWorksheets();
        if ($hasil === []) {
            return [];
        }

        // indeks baris dipertahankan (nomor baris Excel = indeks + 1)
        $penuh = array_fill(0, max(array_keys($hasil)) + 1, []);

        return array_replace($penuh, $hasil);
    }
}
