<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use App\Models\ExamAssignment;
use App\Services\CurrentExamPeriodService;
use App\Services\FehlerKennung;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Perioden-Resolver: pro Request gecacht (Modul 1.1, Phase 1)
        $this->app->scoped(CurrentExamPeriodService::class);

        // SQLite mit deutscher Sortierung (Umlaute wie früher unter MariaDB).
        \Illuminate\Database\Connection::resolverFor('sqlite',
            static fn ($pdo, $database, $prefix, $config) =>
                new \App\Database\LokaleSqliteVerbindung($pdo, $database, $prefix, $config));

        // Fehlerkennung: pro Request EINE Kennung (Modul 6.4). Sie steht
        // gleichzeitig in der Logzeile und im Kurzbefund in der Sitzung —
        // deshalb `scoped` und nicht `bind`, sonst bekaeme jeder Aufrufer eine
        // eigene und die beiden faenden nicht mehr zueinander.
        $this->app->scoped(FehlerKennung::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Archiv-Direktive (Modul 1.1): Views blenden Aktions-Buttons in
        // der schreibgeschützten Archivansicht aus.
        //   @archive ... @endarchive        → nur in der Archivansicht
        //   @unlessarchive ... @endunlessarchive → nur in der aktuellen Periode
        Blade::if('archive', function (): bool {
            return app(CurrentExamPeriodService::class)->isArchiveView();
        });

        // CSP-Nonce für eigene Inline-<script>-Blöcke (Modul 7.2, Stufe 1):
        //   <script @cspNonce> … </script>
        // Die Nonce erzeugt ContentSecurityPolicy pro Request; `@vite` trägt sie
        // selbst ein, diese Direktive ist für alles andere. Ohne gesetzte Nonce
        // (Konsole, PDF-Rendering) gibt sie nichts aus — ein leeres nonce=""
        // wäre unter scharfer CSP ungültig und würde den Block blockieren.
        Blade::directive('cspNonce', function (): string {
            return "<?php \$__cspNonce = \\Illuminate\\Support\\Facades\\Vite::cspNonce(); echo \$__cspNonce ? 'nonce=\"'.e(\$__cspNonce).'\"' : ''; ?>";
        });

        // Explizites Route-Model-Binding für ExamAssignment
        Route::model('assignment', ExamAssignment::class);

    }
}
