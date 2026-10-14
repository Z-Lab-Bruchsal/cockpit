<?php

namespace Database\Seeders;

use App\Enums\SectionLayout;
use App\Models\CompanySetting;
use App\Models\Section;
use Illuminate\Database\Seeder;

/**
 * Initial homepage content for the Z-LAB Bruchsal, based on the public website (www.z-lab-bruchsal.de).
 * Safe to run repeatedly: sections are matched by slug and updated in place.
 */
class HomepageSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCompanySettings();

        $this->seedChildrenAndParents();
        $this->seedSchools();
        $this->seedAboutUs();
        $this->seedContact();
        $legalNotice = $this->seedLegalNotice();
        $privacy = $this->seedPrivacy();

        $this->seedFooter($legalNotice, $privacy);
    }

    protected function seedCompanySettings(): void
    {
        CompanySetting::current()->update([
            'name' => 'Z-LAB',
            'street' => 'Werner-von-Siemens-Str. 2-6',
            'zip' => '76646',
            'city' => 'Bruchsal',
            'contact_phone' => '0176 5055 1776',
            'contact_email' => 'zlab-bruchsal@auerbach-stiftung.de',
            'homepage_title' => 'Z-LAB Bruchsal',
            'homepage_tagline' => 'Nachhaltig begeistern – MINT und Bildung für nachhaltige Entwicklung für Kinder, Jugendliche und Schulen in der Region Bruchsal.',
            'footer_text' => 'Z-LAB: Nachhaltig begeistern',
            'social_links' => [
                ['platform' => 'instagram', 'url' => 'https://www.instagram.com/z.lab.bruchsal'],
                ['platform' => 'facebook', 'url' => 'https://www.facebook.com/AuerbachStiftung/'],
                ['platform' => 'youtube', 'url' => 'https://www.youtube.com/@auerbachstiftung5676'],
            ],
        ]);
    }

    protected function seedChildrenAndParents(): void
    {
        $root = $this->section('kinder-eltern', [
            'title' => 'Für Kinder & Eltern',
            'sort' => 1,
            'layout' => SectionLayout::Highlighted,
            'teaser' => 'Kostenlose Nachmittagskurse, Offenes Tüfteln und der Weg zum Schwarzen Gürtel in MINT.',
            'content' => [
                $this->text('<p>Im Z-LAB können Kinder und Jugendliche ab der Grundschule tüfteln, programmieren, forschen und experimentieren. Alle anmeldepflichtigen Kurse sind <strong>kostenlos</strong>.</p><p>Es gibt zwei Wege ins Z-LAB: das <strong>Offene Tüfteln</strong> ohne Anmeldung und <strong>Kurse mit Anmeldung</strong>, die wir im Newsletter und im Kalender ankündigen.</p>'),
            ],
        ]);

        $this->section('schwarzer-guertel', [
            'parent_id' => $root->id,
            'title' => 'Schwarzer Gürtel in MINT',
            'sort' => 1,
            'teaser' => 'Unser modulares MINT-Programm – vom weißen bis zum schwarzen Gürtel.',
            'content' => [
                $this->text('<p>Der Schwarze Gürtel in MINT ist ein modular aufgebautes Programm für motivierte Schülerinnen und Schüler ab dem Grundschulalter. Wie im Kampfsport arbeitet man sich Gürtel für Gürtel nach oben.</p><ul><li><strong>Weißer Gürtel:</strong> Einstieg über das Offene Tüfteln</li><li><strong>Weitere Gürtel:</strong> anmeldepflichtige Kurse, z. B. Löten, Lasercutter, Datenanalyse</li><li><strong>Auf dem Weg zum Schwarzen Gürtel:</strong> u. a. Teilnahme an „Jugend forscht“ und Mitarbeit als Jugendleiterin oder Jugendleiter</li></ul>'),
            ],
        ]);

        $this->section('offenes-tuefteln', [
            'parent_id' => $root->id,
            'title' => 'Offenes Tüfteln',
            'sort' => 2,
            'teaser' => 'Mittwochs von 14 bis 17 Uhr – einfach vorbeikommen, ohne Anmeldung.',
            'content' => [
                $this->text('<p>Beim Offenen Tüfteln kann jede und jeder ab Klasse 2 ohne Anmeldung vorbeikommen und ausprobieren, was im Z-LAB möglich ist.</p><p><strong>Wann:</strong> mittwochs, 14:00 bis 17:00 Uhr<br><strong>Wichtig:</strong> Kinder unter 11 Jahren nur in Begleitung eines Erwachsenen.</p>'),
            ],
        ]);

        $this->section('nachmittagskurse', [
            'parent_id' => $root->id,
            'title' => 'Kurse am Nachmittag',
            'sort' => 3,
            'teaser' => 'Datenanalyse, Lasercutter, Löten, elektronische Grußkarten und vieles mehr.',
            'content' => [
                $this->text('<p>In unseren Nachmittagskursen lernen Kinder und Jugendliche in kleinen Gruppen über mehrere Termine hinweg ein Thema richtig kennen – zum Beispiel Datenanalyse, den Umgang mit dem Lasercutter, Löten für Anfänger oder elektronische Grußkarten. Dazu kommen Ferienkurse wie Nähen oder Astrofotografie.</p><p>Die Kurse sind kostenlos, die Plätze aber begrenzt. Ist ein Kurs voll, kommst du auf die Warteliste.</p>'),
            ],
        ]);

        $this->section('innovations-werkstatt', [
            'parent_id' => $root->id,
            'title' => 'Innovations-Werkstatt',
            'sort' => 4,
            'teaser' => 'Mit Führerschein für 3D-Drucker, Lasercutter und Lötstation selbstständig arbeiten.',
            'content' => [
                $this->text('<p>Wer regelmäßig an eigenen Projekten arbeiten möchte, kann sich über ein Kursprogramm den Zugang zur Innovations-Werkstatt erarbeiten. Dort lernst du den sicheren Umgang mit Geräten wie 3D-Drucker, Lasercutter und Lötstation. Das Programm kann bis zu einem Jahr dauern – danach darfst du die Werkstatt ohne Anmeldung nutzen.</p>'),
            ],
        ]);

        $this->section('faq', [
            'parent_id' => $root->id,
            'title' => 'Häufige Fragen',
            'sort' => 5,
            'teaser' => 'Was kostet das? Wie melde ich mich an? Was, wenn kein passender Kurs dabei ist?',
            'content' => [
                $this->text('<h3>Was kostet die Teilnahme?</h3><p>Nichts – alle Kurse im Z-LAB sind kostenlos.</p><h3>Wie melde ich mein Kind an?</h3><p>Zum Offenen Tüfteln braucht es keine Anmeldung. Für alle anderen Kurse meldest du dein Kind über die Homepage an. Ist ein Kurs voll, gibt es eine Warteliste.</p><h3>Es ist kein passender Kurs dabei oder wir haben zu den Kurszeiten keine Zeit.</h3><p>Schreib uns eine kurze Nachricht – wir suchen gemeinsam einen Schnuppertermin.</p>'),
            ],
        ]);
    }

    protected function seedSchools(): void
    {
        $root = $this->section('fuer-schulen', [
            'title' => 'Für Schulen',
            'sort' => 2,
            'teaser' => 'Mit dem Begeisterbus ins Klassenzimmer oder mit der Klasse ins Z-LAB – kostenlos.',
            'content' => [
                $this->text('<p>Wir bringen praktischen MINT- und BNE-Unterricht direkt an die Schulen der Region – von der Robotik bis zum Klimaschutz. Alternativ kommt die Klasse zu uns ins Z-LAB. Alle Angebote sind kostenlos.</p>'),
            ],
        ]);

        $this->section('begeisterbus', [
            'parent_id' => $root->id,
            'title' => 'Der Begeisterbus',
            'sort' => 1,
            'layout' => SectionLayout::FullWidth,
            'teaser' => 'Unser mobiles Klassenzimmer kommt an Schulen im Umkreis von 20 km.',
            'content' => [
                $this->text('<p>Der Begeisterbus ist ein mobiles Klassenzimmer, das wir gemeinsam mit der Hopp-Foundation betreiben. Er besucht Schulen im Umkreis von etwa 20 km um Bruchsal und bringt Workshops zu Informatik, Robotik, Klima und Natur direkt auf den Schulhof.</p>'),
            ],
        ]);

        $this->section('informatik-robotik', [
            'parent_id' => $root->id,
            'title' => 'Informatik & Robotik',
            'sort' => 2,
            'teaser' => 'Programmieren lernen – von mTiny in Klasse 2 bis BOB3 in Klasse 10.',
            'content' => [
                $this->text('<ul><li><strong>Klasse 2 – mTiny:</strong> Robotik ganz ohne Bildschirm, mit Puzzleteilen auf dem Boden</li><li><strong>Klasse 3 – Blue-Bot:</strong> Aufgaben lösen mit kleinen Lernrobotern</li><li><strong>Klasse 4–5 – BOB3 oder Calliope mini:</strong> blockbasiertes Programmieren mit Mikrocontrollern (iPads mit App nötig)</li><li><strong>Klasse 4–7 – Mabot:</strong> einen selbst gebauten Roboter programmieren</li><li><strong>Klasse 6–10 – BOB3 textuell:</strong> textbasiertes Programmieren im eigenen Tempo</li></ul>'),
            ],
        ]);

        $this->section('klima-natur', [
            'parent_id' => $root->id,
            'title' => 'Klima & Natur',
            'sort' => 3,
            'teaser' => 'Messen, experimentieren und verstehen, wie Klima, Energie und Rohstoffe zusammenhängen.',
            'content' => [
                $this->text('<ul><li><strong>Klasse 3–6 – Bäume und unser Klima:</strong> Klimaleistung von Bäumen und ökologischer Fußabdruck (Mai bis Oktober)</li><li><strong>Klasse 3–7 – Clean Energy:</strong> Sonnenenergie und Brennstoffzellen mit Fischertechnik</li><li><strong>Klasse 5–7 – Messtechnik mit Calliope:</strong> Temperatur, Bodenfeuchte und CO₂ messen</li><li><strong>Klasse 5–9 – Klimaexperimente:</strong> Treibhauseffekt und Erderwärmung im Versuch</li><li><strong>Klasse 6–9 – Wasserstoff in Aktion:</strong> von der Elektrolyse zur Brennstoffzelle</li><li><strong>Ab Klasse 8 – Das Weltspiel:</strong> Smartphone Global oder Klimagerechtigkeit (mit EPIZ)</li></ul>'),
            ],
        ]);

        $this->section('forschen-verstehen', [
            'parent_id' => $root->id,
            'title' => 'Forschen & Verstehen',
            'sort' => 4,
            'teaser' => 'Mathematik, Energie und Physik zum Anfassen.',
            'content' => [
                $this->text('<ul><li><strong>Klasse 3–6 – Energie experimentell:</strong> Energieformen und ihre Umwandlung im Versuch</li><li><strong>Klasse 4–5 – Die Mathematik hinter dem Zufall:</strong> Kombinatorik und Wahrscheinlichkeit (mit der PH Karlsruhe)</li><li><strong>Klasse 5–9 – Physik mit Handys:</strong> das Smartphone als Messgerät (Apps „Phyphox“ und „BirdNET“ vorab installieren)</li></ul>'),
            ],
        ]);

        $this->section('making', [
            'parent_id' => $root->id,
            'title' => 'Making',
            'sort' => 5,
            'teaser' => 'Mit Maschinen arbeiten – als Klassenwerkstatt oder beim Schulfest.',
            'content' => [
                $this->text('<ul><li><strong>Klassenwerkstatt:</strong> Die Klasse gestaltet am Computer eine Holzbox und schneidet sie im Bus mit dem Lasercutter aus.</li><li><strong>Schulfest:</strong> Der Begeisterbus kommt als Mitmach-Attraktion zu eurem Fest.</li></ul><p>Für beide Angebote stimmen wir uns vorher mit euch ab.</p>'),
            ],
        ]);

        $this->section('klassen-im-z-lab', [
            'parent_id' => $root->id,
            'title' => 'Mit der Klasse ins Z-LAB',
            'sort' => 6,
            'teaser' => 'Planspiele und spektakuläre Experimente bei uns vor Ort.',
            'content' => [
                $this->text('<ul><li><strong>5. Klassen – Magie der Wissenschaften:</strong> Zauberei oder Physik? Spektakuläre Experimente zum Staunen</li><li><strong>Ab Klasse 8 – Das Weltspiel:</strong> Smartphone Global oder Klimagerechtigkeit</li><li><strong>Ab Klasse 9 – Klimakabinett:</strong> Planspiel, in dem die Klasse als Ministerien einen Klimaschutzplan beschließt (montags, max. 2 Stunden)</li></ul>'),
            ],
        ]);

        $this->section('schulen-anfrage', [
            'parent_id' => $root->id,
            'title' => 'Anfrage & Lehrkräfte-Fortbildung',
            'sort' => 7,
            'teaser' => 'So kommt der Begeisterbus an eure Schule.',
            'content' => [
                $this->text('<p>Für Lehrkräfte bieten wir im Anschluss an einen Schülerworkshop eine 90-minütige Fortbildung an. Gebt das bitte gleich bei eurer Anfrage mit an.</p>'),
                [
                    'type' => 'call_to_action',
                    'data' => [
                        'heading' => 'Workshop anfragen',
                        'text' => 'Ansprechpartnerin für den Begeisterbus: Dr. Sylvia Zinser',
                        'button_label' => 'E-Mail schreiben',
                        'button_url' => 'mailto:sylvia.zinser@auerbach-stiftung.de',
                    ],
                ],
            ],
        ]);
    }

    protected function seedAboutUs(): void
    {
        $root = $this->section('ueber-uns', [
            'title' => 'Über uns',
            'sort' => 3,
            'teaser' => 'Das Zukunftslabor der Auerbach Stiftung – für die Zukunft der Region.',
            'content' => [
                $this->text('<p>Das Z-LAB ist ein langfristiges, gemeinnütziges Projekt der Auerbach Stiftung. Gemeinsam mit Partnern aus Bildung, Wissenschaft und Kommunen bauen wir ein Netzwerk aus Schulen und Unternehmen auf und bieten Kurse und Events für Schülerinnen und Schüler an. Unser Ziel: Bruchsal und die umliegenden Gemeinden zu einer MINT- und BNE-Region zu machen.</p>'),
            ],
        ]);

        $this->section('ansatz', [
            'parent_id' => $root->id,
            'title' => 'Unser Ansatz',
            'sort' => 1,
            'teaser' => 'Praxis statt Theorie – und die 17 Nachhaltigkeitsziele der UN als Kompass.',
            'content' => [
                $this->text('<p>Neben Fachwissen brauchen junge Menschen heute vor allem Problemlösekompetenz, Teamfähigkeit, Flexibilität und die Bereitschaft, sich auf Neues einzulassen. Deshalb steht bei uns die Praxis im Vordergrund: basteln, programmieren, forschen und experimentieren – immer mit Bezug zum Alltag.</p><p>Ein Kernthema ist Bildung für nachhaltige Entwicklung (BNE). Orientierung geben uns die 17 Nachhaltigkeitsziele der Vereinten Nationen.</p>'),
            ],
        ]);

        $this->section('team', [
            'parent_id' => $root->id,
            'title' => 'Das Team',
            'sort' => 2,
            'teaser' => 'Die Menschen hinter dem Z-LAB.',
            'content' => [
                $this->text('<ul><li><strong>Steffen Heil</strong> – Leitung</li><li><strong>Dr. Sylvia Zinser</strong> – Experimentalphysik</li><li><strong>Carina Baumgärtner-Huber</strong> – Lehrerin</li><li><strong>Oleg Pukhlyak</strong> – Informatik und Robotik</li><li><strong>Anastasiia Dmitrieva</strong> – Jugend forscht</li><li><strong>Alexandra Schellbach</strong> – Pädagogische Fachberatung</li><li><strong>Jürgen Riffel</strong> – Future Alliance</li><li><strong>Dr. Bernd Hoffmann</strong> – Ehrenamtlicher Referent für Chemie</li><li><strong>Anton Schneider</strong> – Mentorenprogramm</li><li><strong>Alexander Gabriel</strong> – Ehrenamtlicher IT-Administrator</li></ul>'),
            ],
        ]);

        $this->section('stiftung', [
            'parent_id' => $root->id,
            'title' => 'Auerbach Stiftung',
            'sort' => 3,
            'teaser' => 'Träger des Z-LAB.',
            'content' => [
                $this->text('<p>Das Z-LAB wird von der Auerbach Stiftung getragen, einer rechtsfähigen öffentlichen Stiftung des bürgerlichen Rechts.</p>'),
                [
                    'type' => 'call_to_action',
                    'data' => [
                        'heading' => 'Mehr über die Stiftung',
                        'text' => 'Projekte, Spenden und Newsletter der Auerbach Stiftung',
                        'button_label' => 'Zur Stiftung',
                        'button_url' => 'https://www.auerbach-stiftung.de/auerbach-stiftung-72-de',
                    ],
                ],
            ],
        ]);
    }

    protected function seedContact(): Section
    {
        return $this->section('kontakt', [
            'title' => 'Kontakt & Anfahrt',
            'sort' => 4,
            'teaser' => 'Im Triwo Technopark Bruchsal, Gebäude 5137c (Hubwerk01).',
            'content' => [
                [
                    'type' => 'contact',
                    'data' => [
                        'intro' => '<p>Du hast Fragen zu unseren Kursen oder möchtest einen Schnuppertermin vereinbaren? Ruf uns an oder schreib uns.</p><p><strong>So findest du uns:</strong> Das Z-LAB befindet sich im Triwo Technopark, Gebäude 5137c (Hubwerk01). Vom Bahnhof Bruchsal erreichst du uns gut zu Fuß.</p>',
                    ],
                ],
            ],
        ]);
    }

    protected function seedLegalNotice(): Section
    {
        return $this->section('impressum', [
            'title' => 'Impressum',
            'sort' => 90,
            'is_standalone' => true,
            'show_in_navigation' => false,
            'teaser' => null,
            'content' => [
                $this->text('<h2>Angaben gemäß § 5 DDG</h2><p>Auerbach Stiftung<br>Rechtsfähige öffentliche Stiftung des bürgerlichen Rechts mit Sitz in Nürnberg<br>Betreiberin des Z-LAB, des Zukunftslabors der Auerbach Stiftung</p><p>Werner-von-Siemens-Str. 2-6<br>Gebäude 5137c (Hubwerk01)<br>76646 Bruchsal</p><h2>Vertreten durch den Vorstand</h2><p>Steffen Heil<br>Tjark Auerbach</p><h2>Kontakt</h2><p>Telefon: +49 (0) 176 5055 1776<br>E-Mail: zlab-bruchsal@auerbach-stiftung.de</p><h2>Umsatzsteuer-ID</h2><p>Umsatzsteuer-Identifikationsnummer gemäß § 27a Umsatzsteuergesetz: DE81 5611 208</p><h2>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2><p>Steffen Heil<br>Werner-von-Siemens-Str. 2-6, 76646 Bruchsal</p><h2>Verbraucherstreitbeilegung</h2><p>Wir sind nicht bereit oder verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>'),
            ],
        ]);
    }

    protected function seedPrivacy(): Section
    {
        return $this->section('datenschutz', [
            'title' => 'Datenschutz',
            'sort' => 91,
            'is_standalone' => true,
            'show_in_navigation' => false,
            'teaser' => null,
            'content' => [
                $this->text('<p>Die Datenschutzerklärung für diese Seite wird gerade erstellt. Bis dahin gilt die <a href="https://www.auerbach-stiftung.de/datenschutz-10-de">Datenschutzerklärung der Auerbach Stiftung</a>.</p>'),
            ],
        ]);
    }

    protected function seedFooter(Section $legalNotice, Section $privacy): void
    {
        CompanySetting::current()->update([
            'footer_links' => [
                ['label' => 'Kontakt & Anfahrt', 'section_id' => Section::query()->where('slug', 'kontakt')->value('id'), 'url' => null],
                ['label' => 'Auerbach Stiftung', 'section_id' => null, 'url' => 'https://www.auerbach-stiftung.de'],
                ['label' => 'Impressum', 'section_id' => $legalNotice->id, 'url' => null],
                ['label' => 'Datenschutz', 'section_id' => $privacy->id, 'url' => null],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function section(string $slug, array $attributes): Section
    {
        return Section::query()->updateOrCreate(['slug' => $slug], [
            'parent_id' => null,
            'is_visible' => true,
            'show_title' => true,
            'show_in_navigation' => true,
            'is_standalone' => false,
            'layout' => SectionLayout::Standard,
            ...$attributes,
        ]);
    }

    /**
     * @return array{type: string, data: array{body: string}}
     */
    protected function text(string $html): array
    {
        return ['type' => 'text', 'data' => ['body' => $html]];
    }
}
