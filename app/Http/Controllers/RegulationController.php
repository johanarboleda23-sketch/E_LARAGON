<?php

namespace App\Http\Controllers;

use App\Support\ColombianRegulationCatalog;
use Illuminate\Http\Request;

class RegulationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'sector' => 'nullable|string|in:'.implode(',', array_keys(ColombianRegulationCatalog::SECTORS)),
        ]);

        $keyword = mb_strtolower(trim($data['q'] ?? ''));
        $sector = $data['sector'] ?? null;

        $results = collect(ColombianRegulationCatalog::all())
            ->when($sector, fn ($items) => $items->where('sector', $sector))
            ->when($keyword !== '', fn ($items) => $items->filter(
                fn (array $item) => str_contains(mb_strtolower($item['title']), $keyword)
                    || str_contains(mb_strtolower($item['keywords']), $keyword)
            ))
            ->values();

        return view('regulations.index', [
            'results' => $results,
            'sectors' => ColombianRegulationCatalog::SECTORS,
            'query' => $data['q'] ?? '',
            'selectedSector' => $sector,
        ]);
    }
}
