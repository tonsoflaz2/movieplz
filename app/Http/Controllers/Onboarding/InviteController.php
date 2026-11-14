<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Host;
use App\Models\Invite;
use App\Support\InviteCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InviteController extends Controller
{
    /**
     * POST /onboarding/invite
     *
     * Validates a 5-digit code, checks it's still usable, and returns
     * the name-entry partial on success. On any failure (bad format,
     * unknown code, expired, already-redeemed) returns the invite form
     * partial with an inline error message — still at HTTP 200, because
     * the hypeapps shell discards non-2xx response bodies.
     */
    public function redeem(Request $request)
    {
        $rawCode = (string) $request->input('code', '');
        $code = InviteCode::normalize($rawCode);

        // The HAM client posts an all-digit field as a number, so "05404"
        // arrives as "5404". Codes are always LENGTH digits: put the zeros back.
        if (preg_match('/^\d{1,'.(InviteCode::LENGTH - 1).'}$/', $code)) {
            $code = str_pad($code, InviteCode::LENGTH, '0', STR_PAD_LEFT);
        }

        $validator = Validator::make(['code' => $code], [
            'code' => ['required', 'string', 'size:'.InviteCode::LENGTH, 'regex:/^\d+$/'],
        ], [
            'code.required' => 'Enter your 5-digit code.',
            'code.size' => 'Codes are 5 digits.',
            'code.regex' => 'Codes are 5 digits.',
        ]);

        if ($validator->fails()) {
            return $this->formWithError($validator->errors()->first('code'), $rawCode);
        }

        $invite = $this->reviewInvite($code) ?? Invite::query()->where('code', $code)->first();

        if (! $invite || ! $invite->isUsable()) {
            return $this->formWithError('Code not recognized or expired.', $rawCode);
        }

        $request->session()->put('pending_invite_id', $invite->id);

        return response()
            ->view('ham.onboarding.name', ['invite' => $invite])
            ->header('Content-Type', 'text/html');
    }

    /**
     * The App Review code (config/movieplz.php) joins the demo library: a
     * fresh one-time invite for it, so the usual flow and checks run.
     */
    private function reviewInvite(string $code): ?Invite
    {
        $reviewCode = (string) config('movieplz.review_code');
        if ($reviewCode === '' || $code !== $reviewCode) {
            return null;
        }
        $host = Host::where('name', config('movieplz.review_host'))->first();
        if (! $host) {
            return null;
        }

        return Invite::create([
            'host_id' => $host->id,
            'code' => InviteCode::generate(),
            'grants_host' => false,
            'expires_at' => now()->addMinutes(InviteCode::LIFETIME_MINUTES),
        ]);
    }

    private function formWithError(string $message, string $previousCode)
    {
        return response()
            ->view('ham.onboarding.join', [
                'error' => $message,
                'code' => $previousCode,
            ])
            ->header('Content-Type', 'text/html');
    }
}
