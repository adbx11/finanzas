<?php

namespace App\Http\Controllers;

use App\Services\BackupStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BackupController extends Controller
{
    public function __construct(
        private BackupStatusService $backups,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Backup/Index', [
            'status' => $this->backups->status(),
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $disk = $request->input('disk');
        if ($disk !== null && $disk !== '') {
            $allowed = $this->backups->destinationDisks();
            if (! in_array($disk, $allowed, true)) {
                return redirect()
                    ->route('backup.index')
                    ->with('error', 'Disco de destino inválido.');
            }
        } else {
            $disk = null;
        }

        $result = $this->backups->run($disk);

        if ($result['ok']) {
            return redirect()
                ->route('backup.index')
                ->with('success', $result['message']);
        }

        return redirect()
            ->route('backup.index')
            ->with('error', $result['message']);
    }
}
