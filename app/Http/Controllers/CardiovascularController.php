<?php

namespace App\Http\Controllers;

use App\Models\CardiovascularPatient;
use Illuminate\Http\Request;

class CardiovascularController extends Controller
{
    // GET /api/cardiovascular (Daftar Pasien + Paginasi)
    public function index(Request $request)
    {
        $limit = $request->query('limit', 15);
        $patients = CardiovascularPatient::paginate($limit);

        return response()->json([
            'status' => 'success',
            'message' => 'Data rekam medis pasien berhasil diambil',
            'data' => $patients,
        ], 200);
    }

    // GET /api/cardiovascular/{patientid} (Detail Satu Pasien)
    public function show($patientid)
    {
        $patient = CardiovascularPatient::where('patientid', $patientid)->first();

        if (! $patient) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data pasien tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Detail data pasien ditemukan',
            'data' => $patient,
        ], 200);
    }

    // GET /api/cardiovascular/filter (Penyaringan Berdasarkan Parameter Klinis)
    public function filter(Request $request)
    {
        $query = CardiovascularPatient::query();

        if ($request->has('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->has('target')) {
            $query->where('target', $request->target);
        }

        if ($request->has('min_age')) {
            $query->where('age', '>=', $request->min_age);
        }

        if ($request->has('max_age')) {
            $query->where('age', '<=', $request->max_age);
        }

        $results = $query->paginate($request->query('limit', 15));

        return response()->json([
            'status' => 'success',
            'message' => 'Hasil filter data pasien',
            'filters_applied' => $request->only(['gender', 'target', 'min_age', 'max_age']),
            'data' => $results,
        ], 200);
    }

    // GET /api/cardiovascular/statistics (Agregasi Statistik Klinis)
    public function statistics()
    {
        $total = CardiovascularPatient::count();
        $berisiko = CardiovascularPatient::where('target', 1)->count();
        $normal = CardiovascularPatient::where('target', 0)->count();
        $avgCholesterol = CardiovascularPatient::avg('serumcholestrol');
        $avgHeartRate = CardiovascularPatient::avg('maxheartrate');

        return response()->json([
            'status' => 'success',
            'message' => 'Statistik agregat dataset kardiovaskular',
            'data' => [
                'total_pasien' => $total,
                'pasien_berisiko' => $berisiko,
                'pasien_normal' => $normal,
                'persentase_berisiko' => $total > 0 ? round(($berisiko / $total) * 100, 2).'%' : '0%',
                'rata_rata_kolesterol' => round($avgCholesterol, 2),
                'rata_rata_detak_jantung_maksimum' => round($avgHeartRate, 2),
            ],
        ], 200);
    }
}
