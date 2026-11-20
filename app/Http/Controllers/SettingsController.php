<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\WhatsAppMessageService;
use App\Support\ValidationMessages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController
{
    public function edit(WhatsAppMessageService $whatsApp): View
    {
        $template = $whatsApp->template();

        return view('settings.index', [
            'v' => [
                'studio_name' => Setting::get('studio_name', 'Active Barn'),
                'studio_address' => Setting::get('studio_address', ''),
                'studio_phone' => Setting::get('studio_phone', ''),
                'checkin_open_minutes' => Setting::int('checkin_open_minutes', 30),
                'arrive_early_minutes' => Setting::int('arrive_early_minutes', 10),
                'cancel_limit_hours' => Setting::int('cancel_limit_hours', 3),
            ],
            'template' => $template,
            'preview' => $whatsApp->renderSample($template),
            'placeholders' => WhatsAppMessageService::PLACEHOLDERS,
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'studio_name' => ['required', 'string', 'max:100'],
            'studio_address' => ['nullable', 'string', 'max:255'],
            'studio_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]*$/'],
            'checkin_open_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'arrive_early_minutes' => ['required', 'integer', 'min:0', 'max:120', 'lte:checkin_open_minutes'],
            'cancel_limit_hours' => ['required', 'integer', 'min:0', 'max:72'],
        ], ValidationMessages::messages(), ValidationMessages::attributes());

        foreach ($data as $key => $value) {
            Setting::set($key, $value === null ? null : (string) $value);
        }

        return redirect()->route('settings.index')->with('status', 'Pengaturan disimpan.');
    }

    public function updateTemplate(Request $request, WhatsAppMessageService $whatsApp): RedirectResponse
    {
        $data = $request->validate([
            'template' => ['required', 'string', 'max:2000', function (string $attribute, mixed $value, \Closure $fail) use ($whatsApp) {
                $unknown = $whatsApp->unknownPlaceholders((string) $value);
                if ($unknown !== []) {
                    $fail('Placeholder tidak dikenal: ' . implode(', ', $unknown) . '. Pilih dari daftar di bawah kolom teks.');
                }
            }],
        ], ValidationMessages::messages(), ValidationMessages::attributes());

        Setting::set('whatsapp_template', str_replace("\r\n", "\n", $data['template']));

        return redirect()->route('settings.index')->with('status', 'Template pesan disimpan.');
    }

    public function resetTemplate(): RedirectResponse
    {
        Setting::set('whatsapp_template', WhatsAppMessageService::DEFAULT_TEMPLATE);

        return redirect()->route('settings.index')->with('status', 'Template pesan dikembalikan ke bawaan.');
    }

    /** Pratinjau langsung saat mengetik, dengan data contoh. */
    public function previewTemplate(Request $request, WhatsAppMessageService $whatsApp): JsonResponse
    {
        $data = $request->validate(['template' => ['required', 'string', 'max:2000']]);

        return response()->json([
            'text' => $whatsApp->renderSample($data['template']),
            'unknown' => $whatsApp->unknownPlaceholders($data['template']),
        ]);
    }
}
