<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    /**
     * Offizieller bayerischer Fächerkatalog mit den Eigenschaften, die
     * §§ 48 und 61 GSO für die Fächerwahl brauchen.
     *
     * DIES IST DIE EINZIGE QUELLE DES KATALOGS. Wer eine Eigenschaft per
     * Migration ändert, muss sie hier nachziehen — sonst macht der nächste
     * Seeder-Lauf die Migration wieder rückgängig. Genau das war seit
     * 2026_06_18_100001_fix_subjects_aufgabenfeld_and_can_abitur der Fall:
     * Migrationen laufen vor Seedern, also bekam jede frische Installation
     * den falschen Stand zurück. SubjectCatalogTest hält das jetzt fest.
     *
     * @return list<array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            // ================================================================
            // AUFGABENFELD I: SPRACHLICH-LITERARISCH-KÜNSTLERISCH
            // ================================================================
            [
                'code' => 'D',
                'name' => 'Deutsch',
                'color' => '#DC2626',
                'aufgabenfeld' => 'sprachlich_literarisch',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 10,
                'can_abitur' => true,
            ],
            
            // Fremdsprachen
            [
                'code' => 'E',
                'name' => 'Englisch',
                'color' => '#2563EB',
                'aufgabenfeld' => 'sprachlich_literarisch',
                'is_foreign_language' => true,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 20,
                'can_abitur' => true,
            ],
            [
                'code' => 'F',
                'name' => 'Französisch',
                'color' => '#7C3AED',
                'aufgabenfeld' => 'sprachlich_literarisch',
                'is_foreign_language' => true,
                'is_science' => false,
                'can_be_late_starting' => true,
                'sort_order' => 30,
                'can_abitur' => true,
            ],
            // "Spät beginnend" ist im Katalog doppelt modelliert: als Flag
            // can_be_late_starting auf F/Sp/It/Ru und als eigenes Fach Fs.
            // Beides bleibt — an der Pilotschule läuft ein Fs-Kurs. Für § 61 Abs. 3 S. 7
            // zählt ohnehin, welche der gewählten Sprachen die zweite ist,
            // nicht wie sie im Katalog heißt (offen für Modul 2.1, Punkt 4).
            [
                'code' => 'Fs',
                'name' => 'Französisch spätbeginnend',
                'color' => '#7C3AED',
                'aufgabenfeld' => 'sprachlich_literarisch',
                'is_foreign_language' => true,
                'is_science' => false,
                'can_be_late_starting' => true,
                'sort_order' => 35,
                'can_abitur' => true,
            ],
            [
                'code' => 'Sp',
                'name' => 'Spanisch',
                'color' => '#EA580C',
                'aufgabenfeld' => 'sprachlich_literarisch',
                'is_foreign_language' => true,
                'is_science' => false,
                'can_be_late_starting' => true,
                'sort_order' => 40,
                'can_abitur' => true,
            ],
            [
                'code' => 'L',
                'name' => 'Latein',
                'color' => '#92400E',
                'aufgabenfeld' => 'sprachlich_literarisch',
                'is_foreign_language' => true,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 50,
                'can_abitur' => true,
            ],
            [
                'code' => 'It',
                'name' => 'Italienisch',
                'color' => '#059669',
                'aufgabenfeld' => 'sprachlich_literarisch',
                'is_foreign_language' => true,
                'is_science' => false,
                'can_be_late_starting' => true,
                'sort_order' => 60,
                'can_abitur' => true,
            ],
            [
                'code' => 'Ru',
                'name' => 'Russisch',
                'color' => '#DC2626',
                'aufgabenfeld' => 'sprachlich_literarisch',
                'is_foreign_language' => true,
                'is_science' => false,
                'can_be_late_starting' => true,
                'sort_order' => 70,
                'can_abitur' => true,
            ],
            
            // Anlage 3 führt Griechisch und Türkisch im Fachbereich
            // "Fremdsprachen" des Aufgabenfelds SLK — ohne das Flag zählen sie
            // nicht für die zwei nach § 61 Abs. 1 S. 2 geforderten
            // Fremdsprachen. An der Pilotschule läuft ein Türkisch-Kurs.
            //
            // Anlage 3 unterscheidet je Sprache zwischen "fortgeführt" und
            // "spät beginnend": Griechisch gibt es nur fortgeführt, Türkisch
            // (wie Polnisch und Tschechisch) nur spät beginnend.
            [
                'code' => 'Gr',
                'name' => 'Griechisch',
                'color' => '#0891B2',
                'aufgabenfeld' => 'sprachlich_literarisch',
                'is_foreign_language' => true,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 80,
                'can_abitur' => true,
            ],
            [
                'code' => 'Trs',
                'name' => 'Türkisch',
                'color' => '#E11D48',
                'aufgabenfeld' => 'sprachlich_literarisch',
                'is_foreign_language' => true,
                'is_science' => false,
                'can_be_late_starting' => true,
                'sort_order' => 90,
                'can_abitur' => true,
            ],

            // Künstlerische Fächer
            [
                'code' => 'Mu',
                'name' => 'Musik',
                'color' => '#BE123C',
                'aufgabenfeld' => 'sprachlich_literarisch',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 110,
                'can_abitur' => true,
            ],
            [
                'code' => 'Ku',
                'name' => 'Kunst',
                'color' => '#BE185D',
                'aufgabenfeld' => 'sprachlich_literarisch',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 120,
                'can_abitur' => true,
            ],
            
            // ================================================================
            // AUFGABENFELD II: GESELLSCHAFTSWISSENSCHAFTLICH
            // ================================================================
            [
                'code' => 'G',
                'name' => 'Geschichte',
                'color' => '#9F1239',
                'aufgabenfeld' => 'gesellschaftswissenschaftlich',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 200,
                'can_abitur' => true,
            ],
            [
                'code' => 'Geo',
                'name' => 'Geographie',
                'color' => '#CA8A04',
                'aufgabenfeld' => 'gesellschaftswissenschaftlich',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 210,
                'can_abitur' => true,
            ],
            [
                'code' => 'WR',
                'name' => 'Wirtschaft und Recht',
                'color' => '#0F766E',
                'aufgabenfeld' => 'gesellschaftswissenschaftlich',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 230,
                'can_abitur' => true,
            ],
            [
                'code' => 'Eth',
                'name' => 'Ethik',
                'color' => '#A16207',
                'aufgabenfeld' => 'gesellschaftswissenschaftlich',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 250,
                'can_abitur' => true,
            ],
            [
                'code' => 'PuG',
                'name' => 'Politik und Gesellschaft',
                'color' => '#7C2D12',
                'aufgabenfeld' => 'gesellschaftswissenschaftlich',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 220,
                'can_abitur' => true,
            ],
            // Wirtschaftswissenschaften und Philosophie sind Fächer des
            // Aufgabenfelds II, aber **keine Abiturprüfungsfächer**: § 48
            // Abs. 1 S. 8 lässt nur Fächer aus Anlage 3 und Anlage 4 Nr. 1 zu.
            // Anlage 4 Nr. 1 umfasst allein Wirtschaftsinformatik (WWG) und
            // Sozialwissenschaftliche Arbeitsfelder (SWG); Philosophie steht in
            // Nr. 3.2 ("Fächer ohne Lehrplan"), Wirtschaftswissenschaften in
            // keiner der beiden Anlagen. Entscheidung Wilfried, 26.08.2026.
            [
                'code' => 'Ww',
                'name' => 'Wirtschaftswissenschaften',
                'color' => '#0F766E',
                'aufgabenfeld' => 'gesellschaftswissenschaftlich',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 240,
                'can_abitur' => false,
            ],
            [
                'code' => 'Phi',
                'name' => 'Philosophie',
                'color' => '#4F46E5',
                'aufgabenfeld' => 'gesellschaftswissenschaftlich',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 260,
                'can_abitur' => false,
            ],

            // Religionslehre und Ethik gehören in das Aufgabenfeld II.
            // Die katholische Religionslehre stand bis 25.08.2026 unter
            // "Künstlerische Fächer" und damit im Aufgabenfeld I; die
            // Migration vom 18.06. hatte die orthodoxe Religionslehre genau
            // deswegen korrigiert ("nicht SLK") und die katholische
            // übersehen. Der Wert für Ev stand zuvor in keiner Quelle —
            // er war in der Datenbank von Hand gesetzt.
            [
                'code' => 'K',
                'name' => 'Katholische Religionslehre',
                'color' => '#6B21A8',
                'aufgabenfeld' => 'gesellschaftswissenschaftlich',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 270,
                'can_abitur' => true,
            ],
            [
                'code' => 'Ev',
                'name' => 'Evangelische Religionslehre',
                'color' => '#7E22CE',
                'aufgabenfeld' => 'gesellschaftswissenschaftlich',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 280,
                'can_abitur' => true,
            ],
            [
                'code' => 'Ort',
                'name' => 'Orthodoxe Religionslehre',
                'color' => '#581C87',
                'aufgabenfeld' => 'gesellschaftswissenschaftlich',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 290,
                'can_abitur' => true,
            ],
            
            // ================================================================
            // AUFGABENFELD III: MINT (Mathematik, Informatik, Naturwissenschaften)
            // ================================================================
            [
                'code' => 'M',
                'name' => 'Mathematik',
                'color' => '#0891B2',
                'aufgabenfeld' => 'mint',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 300,
                'can_abitur' => true,
            ],
            
            // Naturwissenschaften (§61 GSO)
            [
                'code' => 'Ph',
                'name' => 'Physik',
                'color' => '#4338CA',
                'aufgabenfeld' => 'mint',
                'is_foreign_language' => false,
                'is_science' => true,
                'can_be_late_starting' => false,
                'sort_order' => 310,
                'can_abitur' => true,
            ],
            [
                'code' => 'C',
                'name' => 'Chemie',
                'color' => '#65A30D',
                'aufgabenfeld' => 'mint',
                'is_foreign_language' => false,
                'is_science' => true,
                'can_be_late_starting' => false,
                'sort_order' => 320,
                'can_abitur' => true,
            ],
            [
                'code' => 'B',
                'name' => 'Biologie',
                'color' => '#16A34A',
                'aufgabenfeld' => 'mint',
                'is_foreign_language' => false,
                'is_science' => true,
                'can_be_late_starting' => false,
                'sort_order' => 330,
                'can_abitur' => true,
            ],
            [
                'code' => 'Inf',
                'name' => 'Informatik',
                'color' => '#1F2937',
                'aufgabenfeld' => 'mint',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 340,
                'can_abitur' => true,
            ],
            
            // ================================================================
            // SPORT UND FÄCHER OHNE AUFGABENFELD
            // (Sport hat ein eigenes Kennzeichen, die übrigen sind keine
            //  Abiturfächer und werden über can_abitur ausgeblendet)
            // ================================================================
            
            [
                'code' => 'DZ',
                'name' => 'Deutsch als Zweitsprache',
                'color' => '#DC2626',
                'aufgabenfeld' => null,
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 920,
                'can_abitur' => false,
            ],
            
            
            
            // Sport ist keinem der drei Aufgabenfelder zugeordnet (§ 18) und
            // wird über § 48 Abs. 5 als besondere Fachprüfung geführt.
            [
                'code' => 'Sm',
                'name' => 'Sport männlich',
                'color' => '#047857',
                'aufgabenfeld' => 'sport',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 400,
                'can_abitur' => true,
            ],
            [
                'code' => 'Sw',
                'name' => 'Sport weiblich',
                'color' => '#047857',
                'aufgabenfeld' => 'sport',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 410,
                'can_abitur' => true,
            ],
            [
                'code' => 'Smw',
                'name' => 'Sport männlich/weiblich',
                'color' => '#047857',
                'aufgabenfeld' => 'sport',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 420,
                'can_abitur' => true,
            ],

            // Kein Abiturfach — wird vom Fächerkatalog der Fächerwahl über
            // can_abitur ausgeblendet (§ 61 Abs. 1 S. 4).
            [
                'code' => 'Psy',
                'name' => 'Psychologie',
                'color' => '#7C3AED',
                'aufgabenfeld' => 'gesellschaftswissenschaftlich',
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 900,
                'can_abitur' => false,
            ],
            [
                'code' => 'BL',
                'name' => 'Beratungslehrer',
                'color' => '#6B7280',
                'aufgabenfeld' => null,
                'is_foreign_language' => false,
                'is_science' => false,
                'can_be_late_starting' => false,
                'sort_order' => 910,
                'can_abitur' => false,
            ],
        ];
    }

    /**
     * Idempotent: gleicht den Bestand Fach für Fach an definitions() an.
     *
     * Läuft ausdrücklich auf der zentralen Verbindung. Ohne diese Festlegung
     * würde ein Aufruf bei initialisiertem Tenant in die Mandantendatenbank
     * schreiben — subjects ist eine zentrale Tabelle (Subject::$connection).
     *
     * created_at wird nur beim Anlegen gesetzt; der bisherige Seeder hat es
     * bei jedem Lauf überschrieben.
     */
    public function run(): void
    {
        $definitions = self::definitions();

        foreach ($definitions as $subject) {
            $values = array_merge($subject, ['updated_at' => now()]);

            $exists = DB::table('subjects')
                ->where('code', $subject['code'])
                ->exists();

            if (! $exists) {
                $values['created_at'] = now();
            }

            DB::table('subjects')->updateOrInsert(
                ['code' => $subject['code']],
                $values
            );
        }

        $this->command?->info('✅ ' . count($definitions) . ' Fächer geseedet');
    }
}
