<?php

namespace App\Http\Controllers;

use App\Models\GoogleDriveSetting;
use App\Services\GoogleDriveService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GoogleDriveOAuthController extends Controller
{
    public function redirect(Request $request, GoogleDriveService $drive): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $state = Str::random(40);
        $request->session()->put('google_drive_oauth_state', $state);

        return redirect()->away($drive->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleDriveService $drive): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless(hash_equals((string) $request->session()->pull('google_drive_oauth_state'), (string) $request->string('state')), 419);

        if ($request->filled('error')) {
            return redirect('/admin/google-drive-integration')->with('drive_error', 'Izin Google Drive dibatalkan.');
        }

        try {
            $drive->exchangeCode($request->string('code')->toString());

            return redirect('/admin/google-drive-integration')->with('drive_success', 'Google Drive berhasil terhubung.');
        } catch (ConnectionException $exception) {
            report($exception);

            return redirect('/admin/google-drive-integration')->with(
                'drive_error',
                'Server PHP tidak dapat menjangkau Google melalui HTTPS. Izinkan php.exe pada firewall/jaringan atau isi GOOGLE_DRIVE_PROXY.',
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect('/admin/google-drive-integration')->with('drive_error', 'OAuth Google gagal: '.$exception->getMessage());
        }
    }

    public function disconnect(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        GoogleDriveSetting::current()->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'connected_email' => null,
            'reauthorization_required_at' => null,
        ]);

        return back()->with('drive_success', 'Google Drive telah diputuskan.');
    }
}
