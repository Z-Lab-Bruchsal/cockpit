<?php

namespace Database\Seeders;

use App\Models\NewsPost;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Current news of the Z-LAB Bruchsal, taken from the public website (auerbach-stiftung.de/news-5-de).
 * Safe to run repeatedly: posts are matched by slug and updated in place. Images are downloaded once
 * to the public disk; if a download fails, the post is created without that image.
 */
class NewsSeeder extends Seeder
{
    protected const IMAGE_BASE_URL = 'https://www.auerbach-stiftung.de/img/uploads/';

    public function run(): void
    {
        $this->post('jahresrueckblick-2025-26', [
            'title' => 'Unser Jahresrückblick 2025/26: 20 besondere Highlights',
            'teaser' => 'Ein Schuljahr voller Ideen, Teamgeist und MINT-Begeisterung! In unserem Jahresrückblick zeigen wir 20 besondere Momente aus dem Z-LAB Bruchsal.',
            'published_at' => '2026-11-05 10:00',
            'image' => ['zlab-highlights-2025-26-cover_297_1793727031.webp', 'Titelbild des Jahresrückblicks 2025/26 des Z-LAB Bruchsal'],
            'content' => [
                $this->text('<p>Ein Schuljahr voller Ideen, Teamgeist und MINT-Begeisterung! In unserem Jahresrückblick zeigen wir 20 besondere Momente aus dem Z-LAB Bruchsal.</p><p>Vielen Dank an alle Kinder und Jugendlichen, Familien, Ehrenamtlichen und Partner, die dieses Schuljahr mitgestaltet haben.</p>'),
                $this->callToAction(
                    'Jahresrückblick ansehen',
                    '20 Highlights · Schuljahr 2025/26 · PDF, ca. 2 MB',
                    'PDF öffnen',
                    'https://www.auerbach-stiftung.de/down/uploads/z-lab_highlights_2025-26_41_1793794264.pdf',
                ),
            ],
        ]);

        $this->post('neura-robotics-zu-gast-im-z-lab', [
            'title' => 'NEURA Robotics zu Gast im Z-LAB',
            'teaser' => 'Am 2. November war NEURA Robotics erstmals bei uns im Z-LAB Bruchsal zu Gast. Es ging um die Zukunft der Robotik in der Region – und unsere Jugendlichen zeigten Drone Soccer, Roboterhunde und ihre Roboterspinne.',
            'published_at' => '2026-11-03 10:00',
            'image' => ['neura-robotics-presentation_293_1793719341.webp', 'Präsentation von NEURA Robotics im Z-LAB Bruchsal'],
            'content' => [
                $this->text('<p>Am 2. November war NEURA Robotics erstmals bei uns im Z-LAB Bruchsal zu Gast. Gemeinsam mit Vertreterinnen und Vertretern regionaler Unternehmen, der TechnologieRegion Karlsruhe und weiteren Akteuren aus dem Technologie- und Innovationsumfeld ging es um die Zukunft der Robotik und das Konzept NEURA Gym. Neben spannenden Präsentationen gab es viele Gespräche darüber, wie Robotik in unserer Region praktisch erlebbar werden kann.</p>'
                    .'<h2>15 Minuten für unsere jungen Technikbegeisterten</h2>'
                    .'<p>Für unseren Beitrag hatten wir 15 Minuten – und die haben unsere Kinder und Jugendlichen genutzt! Den Anfang machte eine Vorführung von Drone Soccer. Anschließend zeigten wir unsere Roboterhunde in Aktion.</p>'
                    .'<p>Ein besonderes Highlight war die Roboterspinne unserer Jugendlichen. Das Team stellte sein eigenes Projekt vor und zeigte live, wie sich die Spinne bewegt. Vor Fachleuten zu erklären, woran man gearbeitet hat, und das Ergebnis vorzuführen, war eine besondere Erfahrung.</p>'),
                $this->image('neura-besuch-roboterspinne_294_1793719326.webp', 'Jugendliche führen ihre selbst gebaute Roboterspinne vor'),
                $this->text('<h2>Neugierig auf die nächste Begegnung</h2>'
                    .'<p>Natürlich hatten wir gehofft, auch einen Roboter von NEURA Robotics vor Ort erleben zu können. Dieses Mal standen Präsentationen und der persönliche Austausch im Mittelpunkt. Wir hoffen, die Roboter bei einer nächsten Gelegenheit live kennenzulernen und selbst ausprobieren zu dürfen.</p>'
                    .'<p><a href="https://neura-robotics.com/products/4ne1/">Den humanoiden Roboter 4NE1 entdecken</a> (externe Website: NEURA Robotics)</p>'),
                $this->image('neura-besuch-drone-soccer_296_1793719298.webp', 'Drone-Soccer-Vorführung beim Besuch von NEURA Robotics'),
                $this->text('<h2>Danke für die Organisation</h2>'
                    .'<p>Ein herzliches Dankeschön an die Regionale Wirtschaftsförderung Bruchsal für die Organisation und die Gelegenheit, unsere Projekte vorzustellen. Vielen Dank auch an NEURA Robotics und alle Gäste für ihr Interesse und den inspirierenden Austausch.</p>'
                    .'<p><a href="https://wfg-bruchsal.de/2026/10/07/regionale-wirtschaftsfoerderung-bruchsal-und-neura-robotics-streben-kooperation-an/">Mehr über die Robotik-Initiative erfahren</a> (externe Website: Regionale Wirtschaftsförderung Bruchsal)</p>'),
                $this->image('neura-besuch-roboterhunde_295_1793719312.webp', 'Roboterhunde in Aktion'),
            ],
        ]);

        $this->post('buergerpreis-2026-fuer-alexander-gabriel', [
            'title' => 'Bürgerpreis 2026 für Alexander Gabriel',
            'teaser' => 'Alexander Gabriel wurde mit dem Bürgerpreis der Sparkasse Kraichgau in der Kategorie „Alltagshelden“ ausgezeichnet. Wir freuen uns riesig mit ihm!',
            'published_at' => '2026-10-31 10:00',
            'image' => ['alexander-gabriel-buergerpreis-2026-cov_280_1793697854.webp', 'Alexander Gabriel bei der Verleihung des Bürgerpreises 2026'],
            'content' => [
                $this->text('<p>Alexander Gabriel wurde mit dem Bürgerpreis der Sparkasse Kraichgau in der Kategorie „Alltagshelden“ ausgezeichnet. Wir freuen uns riesig mit ihm!</p>'
                    .'<p>Im Z-LAB begeistert Alex Kinder und Jugendliche für Informatik und unterstützt uns als ehrenamtlicher IT-Administrator. Mit seinem Wissen, seiner Geduld und seiner Zeit macht er für viele junge Menschen einen Unterschied.</p>'
                    .'<p>Auch unsere Kinder und Jugendlichen waren bei der Preisverleihung dabei und haben mitgefeiert.</p>'
                    .'<p>Lieber Alex, herzlichen Glückwunsch und danke für deinen großartigen Einsatz und Pizza!</p>'
                    .'<p>KraichgauTV stellt Alex und weitere Ausgezeichnete in einem Videobeitrag vor: <a href="https://landfunker.de/so-wars-brgerpreis-2026-sparkasse-kraichgau-ehrt-menschen-die-ihre-zeit-verschenken/">Videobeitrag ansehen</a> (externe Website: Landfunker / KraichgauTV)</p>'),
                $this->image('zlab-team-buergerpreis-2026_281_1793697879.webp', 'Das Z-LAB-Team bei der Verleihung des Bürgerpreises 2026'),
            ],
        ]);

        $this->post('drone-soccer-trifft-ausbildung-bei-sew-eurodrive', [
            'title' => 'Drone Soccer trifft Ausbildung bei SEW-EURODRIVE',
            'teaser' => 'Am 29. Oktober 2026 war unsere Drone-Soccer-Gruppe zu Gast im Ausbildungszentrum von SEW-EURODRIVE in Graben-Neudorf – mit Drohnen, Roboterhunden und spannenden Einblicken in die Ausbildung.',
            'published_at' => '2026-10-30 10:00',
            'image' => ['sew-drone-soccer-news-cover_273_1793293102.webp', 'Drone-Soccer-Gruppe des Z-LAB bei SEW-EURODRIVE'],
            'content' => [
                $this->text('<p>Am 29. Oktober 2026 war unsere Drone-Soccer-Gruppe zu Gast im Ausbildungszentrum von SEW-EURODRIVE in Graben-Neudorf.</p>'
                    .'<p>Gemeinsam mit den Auszubildenden ließen wir Drohnen fliegen, steuerten Roboterhunde und tauschten uns über unsere Projekte aus. Beim anschließenden Rundgang bekamen unsere Jugendlichen spannende Einblicke in die Ausbildungsbereiche. Besonders schön: Die Auszubildenden nahmen sich viel Zeit, erklärten ihre Arbeit und beantworteten unsere Fragen.</p>'
                    .'<p>Das schönste Feedback kam von unseren Jugendlichen selbst: Nach dem Besuch wollten 7 Kinder ein Praktikum bei SEW-EURODRIVE machen!</p>'
                    .'<p>Genau solche Begegnungen machen berufliche Möglichkeiten greifbar. Selbst ausprobieren, hinter die Kulissen schauen und mit Auszubildenden sprechen – das bleibt im Kopf.</p>'
                    .'<p>Und wir haben bereits gemeinsam beschlossen: <strong>Den nächsten Drone Soccer School Cup wollen wir bei SEW-EURODRIVE veranstalten!</strong></p>'
                    .'<p>Ein riesiges Dankeschön an Andy für die Organisation und an das gesamte Ausbildungsteam für die herzliche Betreuung, die spannenden Einblicke und diesen großartigen Tag.</p>'
                    .'<p><a href="https://www.sew-eurodrive.de/karriere/ausbildung-duales-studium/ausbildung-bei-sew-eurodrive/ausbildung-bei-sew-eurodrive.html">Ausbildung bei SEW-EURODRIVE entdecken</a> · <a href="https://www.instagram.com/reel/DfPCMlbNunD/">Video auf Instagram</a></p>'),
                $this->image('sew-drone-soccer-arena_277_1793293039.webp', 'Drone-Soccer-Arena im Ausbildungszentrum von SEW-EURODRIVE'),
                $this->image('sew-drone-soccer-robohund_272_1793293124.webp', 'Jugendliche steuern einen Roboterhund'),
                $this->image('sew-ausbildung-andy-erklaert_279_1793299243.webp', 'Andy erklärt die Ausbildungsbereiche bei SEW-EURODRIVE'),
                $this->image('sew-drone-soccer-austausch_276_1793293055.webp', 'Austausch zwischen Jugendlichen und Auszubildenden'),
            ],
        ]);

        $this->post('drone-soccer-team-bei-enbw', [
            'title' => 'Drone-Soccer-Team bei EnBW',
            'teaser' => 'Am 27. Oktober 2026 waren wir zu Gast im EnBW-Ausbildungszentrum am Rheinhafen-Dampfkraftwerk Karlsruhe – ein Tag voller Technik, Austausch und neuer Perspektiven.',
            'published_at' => '2026-10-28 10:00',
            'image' => ['zlab-enbw-drone-soccer_269_1793202431.jpg', 'Drone-Soccer-Team des Z-LAB im EnBW-Ausbildungszentrum'],
            'content' => [
                $this->text('<p>Am 27. Oktober 2026 waren wir zu Gast im EnBW-Ausbildungszentrum am Rheinhafen-Dampfkraftwerk Karlsruhe. Gemeinsam mit den Auszubildenden erlebten wir einen Tag voller Technik, Austausch und neuer Perspektiven.</p>'
                    .'<p>Für unsere Jugendlichen war dieser Besuch besonders wertvoll: Sie konnten einen möglichen zukünftigen Ausbildungsort von innen kennenlernen und direkt mit den Menschen sprechen, die dort ihre Ausbildung absolvieren.</p>'
                    .'<p>Unsere Jugendlichen brachten ihre eigenen Projekte mit, flogen Drohnen und steuerten zwei Robohunde.</p>'
                    .'<p>Ein besonderes Highlight war die Kraftwerksbesichtigung mit Einblicken in die Generatoranlage und die Leitstelle.</p>'
                    .'<p>Auch das leckere Essen in der Kantine und die Gespräche am gemeinsamen Tisch gehörten dazu. Solche Begegnungen stärken den Zusammenhalt und eröffnen neue Perspektiven.</p>'
                    .'<p>Ein herzliches Dankeschön an das gesamte Team des EnBW-Ausbildungszentrums, besonders an Jana und Max für die Einladung, die Zeit und diesen besonderen Einblick in eure Arbeit!</p>'
                    .'<p>Die schönsten Momente unseres Besuchs gibt es auch im Video: <a href="https://www.instagram.com/reel/DfCu9oEugjv/">Video auf Instagram ansehen</a></p>'),
                $this->image('zlab-enbw-kraftwerksbesuch_270_1793202470.jpg', 'Besichtigung des Rheinhafen-Dampfkraftwerks Karlsruhe'),
            ],
        ]);

        $this->post('drone-soccer-school-cup-2026', [
            'title' => 'Drone Soccer School Cup 2026',
            'teaser' => 'Was für ein Turniertag am 18. Juli 2026 im Bruchsaler Schlossgarten! Sechs Schulteams, packende Spiele und ein Finale, das mit 31:30 denkbar knapp ausging.',
            'published_at' => '2026-07-20 10:00',
            'image' => ['drone-soccer-school-cup-2026_224_1786251969.jpg', 'Drone Soccer School Cup 2026 im Bruchsaler Schlossgarten'],
            'content' => [
                $this->text('<h2>Im Bruchsaler Schlossgarten</h2>'
                    .'<p>Was für ein Turniertag am 18. Juli 2026 im Bruchsaler Schlossgarten! Beim Drone Soccer School Cup 2026 trafen sechs Teams aus verschiedenen Schulen aufeinander und zeigten eindrucksvoll, wie viel Können, Teamgeist und Begeisterung in diesem noch jungen Sport steckt. Von der ersten Begegnung bis zum packenden Finale wurde um jeden Punkt gekämpft! Und am Ende fiel die Entscheidung denkbar knapp aus.</p>'
                    .'<p>Bereits in der Vorrunde ging es spannend zur Sache. Das Schönborn-Gymnasium setzte sich mit 27:20 gegen das Mixed Team durch, während die Joß-Fritz-Realschule die Albert-Schweitzer-Realschule mit 34:29 bezwang. Im dritten Vorrundenspiel konnte sich das Justus-Knecht-Gymnasium mit 20:16 gegen die Konrad-Adenauer-Schule durchsetzen. Damit qualifizierten sich das Schönborn-Gymnasium, die Joß-Fritz-Realschule und das Justus-Knecht-Gymnasium direkt für das Halbfinale.</p>'
                    .'<p>Für die drei verbleibenden Teams ging es anschließend in der Qualifikationsrunde um den letzten Halbfinalplatz. Aufgrund des straffen Zeitplans wurden die Spiele mit verkürzten, einminütigen Halbzeiten ausgetragen. Die Konrad-Adenauer-Schule setzte sich dabei zunächst mit 11:8 gegen das Mixed Team durch und gewann anschließend mit 11:10 gegen die Albert-Schweitzer-Realschule. Damit war der letzte Halbfinalplatz gesichert.</p>'
                    .'<p>Im Halbfinale warteten dann zwei spannende Duelle: Das Schönborn-Gymnasium überzeugte mit einem deutlichen 33:16 gegen die Konrad-Adenauer-Schule, während sich die Joß-Fritz-Realschule mit 29:21 gegen das Justus-Knecht-Gymnasium durchsetzte. Damit war alles angerichtet für ein großes Finale zwischen der Joß-Fritz-Realschule und dem Schönborn-Gymnasium.</p>'
                    .'<p>Und dieses Finale hielt, was es versprach! In einem packenden und denkbar knappen Spiel setzte sich das Schönborn-Gymnasium mit 31:30 durch und sicherte sich damit den Titel beim Drone Soccer School Cup 2026.</p>'
                    .'<p>Doch eigentlich gab es an diesem Tag keine Verlierer. Denn wer Drone Soccer schon einmal selbst ausprobiert hat, weiß, wie anspruchsvoll dieser Sport ist. Eine Drohne erfolgreich durch das gegnerische Tor zu steuern, erfordert jede Menge Training, höchste Präzision und eine ausgezeichnete Kontrolle. Umso beeindruckender waren die hervorragenden Leistungen, die wir während des gesamten Turniers erleben durften!</p>'
                    .'<p>Ein riesiges Dankeschön geht an alle teilnehmenden Schulen, unsere Partner sowie an alle Helferinnen und Helfer, die diesen besonderen Tag möglich gemacht haben. Ein ganz besonderer Dank gilt außerdem unserem Moderator, der mit seiner großartigen Stimmung, seinem Humor und seiner professionellen Begleitung für die richtige Atmosphäre gesorgt und maßgeblich zum Erfolg des Tages beigetragen hat. Wir hatten wirklich großes Glück, ihn an unserer Seite zu haben!</p>'
                    .'<p>Wir sagen Danke für einen unvergesslichen Turniertag und freuen uns schon jetzt auf den Drone Soccer School Cup 2027!</p>'),
            ],
        ]);
    }

    /**
     * @param  array{title: string, teaser: string, published_at: string, image: array{0: string, 1: string}, content: list<array<string, mixed>>}  $attributes
     */
    protected function post(string $slug, array $attributes): NewsPost
    {
        [$imageFile, $imageAlt] = $attributes['image'];
        $imagePath = $this->downloadImage($imageFile);

        return NewsPost::query()->updateOrCreate(['slug' => $slug], [
            'title' => $attributes['title'],
            'teaser' => $attributes['teaser'],
            'published_at' => $attributes['published_at'],
            'image_path' => $imagePath,
            'image_alt' => $imagePath ? $imageAlt : null,
            'content' => array_values(array_filter($attributes['content'])),
        ]);
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    protected function text(string $html): array
    {
        return ['type' => 'text', 'data' => ['body' => $html]];
    }

    /**
     * Image block, or null (filtered out) if the image could not be downloaded.
     *
     * @return array{type: string, data: array<string, mixed>}|null
     */
    protected function image(string $file, string $alt): ?array
    {
        $path = $this->downloadImage($file);

        return $path ? ['type' => 'image', 'data' => ['image' => $path, 'alt' => $alt, 'caption' => null]] : null;
    }

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    protected function callToAction(string $heading, string $text, string $buttonLabel, string $buttonUrl): array
    {
        return ['type' => 'call_to_action', 'data' => [
            'heading' => $heading,
            'text' => $text,
            'button_label' => $buttonLabel,
            'button_url' => $buttonUrl,
        ]];
    }

    /**
     * Stores an image of the original website on the public disk and returns its path, or null on failure.
     */
    protected function downloadImage(string $file): ?string
    {
        $path = 'homepage/news/'.$file;
        $disk = Storage::disk('public');

        if ($disk->exists($path)) {
            return $path;
        }

        try {
            $response = Http::timeout(20)->get(static::IMAGE_BASE_URL.$file);
        } catch (Throwable) {
            $this->command?->warn("Bild konnte nicht geladen werden: {$file}");

            return null;
        }

        if (! $response->successful() || ! str_starts_with((string) $response->header('Content-Type'), 'image/')) {
            $this->command?->warn("Bild konnte nicht geladen werden: {$file}");

            return null;
        }

        $disk->put($path, $response->body(), 'public');

        return $path;
    }
}
