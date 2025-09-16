<?php

namespace App\Http\Controllers;

use App\Services\TranslationService;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    private function getLanguages(): array
    {
        return config('languages');
    }

    public function translate(Request $request, TranslationService $service)
    {
        $data = json_decode($request->input('json'), true);

        $languages = $this->getLanguages();
        $langName = $languages[$request->input('to')];

        $result = $service->translate($data, $langName);

        return response()->json($result);
    }


    public function listLanguages()
    {
        $languages = $this->getLanguages();

        $formatted = [];
        foreach ($languages as $code => $name) {
            $formatted[] = [
                'code' => $code,
                'name' => $name
            ];
        }

        return response()->json($formatted);
    }

}
