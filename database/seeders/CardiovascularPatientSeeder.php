<?php

namespace Database\Seeders;

use App\Models\CardiovascularPatient;
use Illuminate\Database\Seeder;

class CardiovascularPatientSeeder extends Seeder
{
    public function run(): void
    {
        $csvFile = database_path('data/Cardiovascular_Disease_Dataset.csv');

        if (! file_exists($csvFile)) {
            throw new \RuntimeException("File CSV tidak ditemukan pada path: {$csvFile}");
        }

        $fileHandle = fopen($csvFile, 'r');
        if ($fileHandle === false) {
            throw new \RuntimeException("File CSV tidak dapat dibuka: {$csvFile}");
        }

        $expectedHeader = [
            'patientid',
            'age',
            'gender',
            'chestpain',
            'restingBP',
            'serumcholestrol',
            'fastingbloodsugar',
            'restingrelectro',
            'maxheartrate',
            'exerciseangia',
            'oldpeak',
            'slope',
            'noofmajorvessels',
            'target',
        ];
        $header = fgetcsv($fileHandle, 0, ',');

        if ($header !== $expectedHeader) {
            fclose($fileHandle);
            throw new \RuntimeException('Header CSV tidak sesuai dengan struktur dataset.');
        }

        $batch = [];
        $batchSize = 250;
        $timestamp = now();

        while (($row = fgetcsv($fileHandle, 0, ',')) !== false) {
            if (count($row) !== count($expectedHeader)) {
                fclose($fileHandle);
                throw new \RuntimeException('Ditemukan baris CSV dengan jumlah kolom yang tidak valid.');
            }

            $batch[] = [
                'patientid' => (int) $row[0],
                'age' => (int) $row[1],
                'gender' => (int) $row[2],
                'chestpain' => (int) $row[3],
                'restingBP' => (int) $row[4],
                'serumcholestrol' => (int) $row[5],
                'fastingbloodsugar' => (int) $row[6],
                'restingrelectro' => (int) $row[7],
                'maxheartrate' => (int) $row[8],
                'exerciseangia' => (int) $row[9],
                'oldpeak' => (float) $row[10],
                'slope' => (int) $row[11],
                'noofmajorvessels' => (int) $row[12],
                'target' => (int) $row[13],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            if (count($batch) >= $batchSize) {
                CardiovascularPatient::insert($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            CardiovascularPatient::insert($batch);
        }

        fclose($fileHandle);
    }
}
