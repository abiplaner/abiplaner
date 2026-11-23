<?php

namespace App\Services;

use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Zentrale Kapselung der TOTP-Zwei-Faktor-Logik (Modul 7.1).
 *
 * Einzige Stelle im Projekt, die google2fa und die Trusted-Device-Token
 * verwaltet – analog zum MindestpunkteService als Single Source of Truth.
 * Controller/Middleware rufen ausschliesslich diese Methoden auf.
 */
class TwoFactorService
{
    /**
     * I2: Maximale Gültigkeit eines vertrauenswürdigen Geräts in Tagen.
     * Verhindert, dass ein verlorenes/kompromittiertes Gerät unbegrenzt als
     * zweiter Faktor gilt. Nach Ablauf ist erneut ein TOTP-Code nötig.
     */
    public const TRUST_DURATION_DAYS = 180;

    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA();
    }

    /**
     * Erzeugt ein neues, noch unbestätigtes TOTP-Secret und legt es am Nutzer ab.
     * two_factor_confirmed_at bleibt null, bis confirm() einen gültigen Code sieht.
     */
    public function generateSecret(User $user): string
    {
        $secret = $this->engine->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $secret;
    }

    /**
     * Bestätigt die Einrichtung: prüft den eingegebenen Code gegen das hinterlegte
     * (noch unbestätigte) Secret und aktiviert die 2FA bei Erfolg.
     */
    public function confirm(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        if (! $this->verifyCode($user->two_factor_secret, $code)) {
            return false;
        }

        $user->forceFill([
            'two_factor_confirmed_at' => Carbon::now(),
        ])->save();

        return true;
    }

    /**
     * Prüft einen TOTP-Code gegen ein Secret (mit ±1 Zeitfenster Toleranz).
     */
    public function verifyCode(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if ($code === '') {
            return false;
        }

        return $this->engine->verifyKey($secret, $code, 1) !== false;
    }

    /**
     * Prüft einen TOTP-Code gegen das bestätigte Secret des Nutzers.
     */
    public function verifyForUser(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        return $this->verifyCode($user->two_factor_secret, $code);
    }

    /**
     * Deaktiviert die 2FA vollständig: Secret entfernen und alle vertrauenswürdigen
     * Geräte löschen (z. B. Nutzer-Deaktivierung oder Admin-Reset).
     */
    public function disable(User $user): void
    {
        $user->trustedDevices()->delete();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    /**
     * otpauth://-URI für QR-Code und manuelle Eingabe.
     */
    public function otpauthUri(User $user, string $secret): string
    {
        $issuer = config('app.name', 'Abiplaner');

        return $this->engine->getQRCodeUrl(
            (string) $issuer,
            $user->email,
            $secret,
        );
    }

    /**
     * QR-Code als SVG-String (kein GD/Imagick nötig, DomPDF-unabhängig).
     */
    public function qrCodeSvg(User $user, string $secret, int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd(),
        );

        $writer = new Writer($renderer);

        return $writer->writeString($this->otpauthUri($user, $secret));
    }

    /**
     * QR-Code als data:-URI zur direkten Einbettung in <img src="...">.
     */
    public function qrCodeDataUri(User $user, string $secret, int $size = 200): string
    {
        $svg = $this->qrCodeSvg($user, $secret, $size);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    // ------------------------------------------------------------------
    // Trusted Devices
    // ------------------------------------------------------------------

    /**
     * Erzeugt ein neues Trusted-Device-Token, speichert dessen Hash und gibt
     * das Klartext-Token zurück (wird vom Aufrufer als Cookie gesetzt).
     * I2: expires_at wird auf TRUST_DURATION_DAYS begrenzt (nicht mehr null),
     * damit ein Gerät nicht unbegrenzt vertrauenswürdig bleibt.
     */
    public function rememberDevice(User $user, ?string $label = null, ?string $ip = null): string
    {
        $token = Str::random(64);

        $user->trustedDevices()->create([
            'token_hash' => hash('sha256', $token),
            'device_label' => $label,
            'ip_address' => $ip,
            'last_used_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addDays(self::TRUST_DURATION_DAYS),
        ]);

        return $token;
    }

    /**
     * Ist das übergebene Token (aus dem Cookie) ein gültiges, nicht abgelaufenes
     * vertrauenswürdiges Gerät des Nutzers? Aktualisiert bei Treffer last_used_at.
     */
    public function isTrustedDevice(User $user, ?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $device = TwoFactorTrustedDevice::query()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($device === null || $device->isExpired()) {
            return false;
        }

        $device->forceFill(['last_used_at' => Carbon::now()])->save();

        return true;
    }

    /**
     * Entfernt alle vertrauenswürdigen Geräte eines Nutzers
     * ("alle Geräte abmelden" / Admin-Reset).
     */
    public function forgetAllDevices(User $user): void
    {
        $user->trustedDevices()->delete();
    }
}
