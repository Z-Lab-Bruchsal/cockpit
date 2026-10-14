<?php

namespace Database\Seeders;

use App\Enums\CourseAudience;
use App\Enums\CourseCardStyle;
use App\Enums\CourseDetailLayout;
use App\Enums\CourseInstanceStatus;
use App\Models\Course;
use App\Models\CourseInstance;
use App\Models\CourseTemplate;
use App\Models\Interest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Interests, presentation templates and the autumn 2026 courses of the Z-LAB Bruchsal
 * (www.z-lab-bruchsal.de). Safe to run repeatedly.
 *
 * Capacities and the times of the holiday courses are not published on the website and
 * are placeholders that should be checked in the cockpit. The first user becomes responsible for all courses.
 */
class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $interests = $this->seedInterests();
        $templates = $this->seedTemplates();

        $this->course('Datenanalyse', $interests, ['informatik', 'mathematik'], [
            'teaser' => 'Daten sammeln, auswerten und verständlich darstellen.',
            'description' => '<p>Wie viele Schritte gehe ich am Tag? Welches Wetter hatten wir im Sommer? In diesem Kurs lernst du, Daten zu sammeln, auszuwerten und in Diagrammen darzustellen.</p>',
            'duration' => 75,
        ], $this->weekly('2026-09-22 16:00', 5, 75));

        $this->course('Lasercutter', $interests, ['making'], [
            'teaser' => 'Eigene Designs am Computer entwerfen und mit dem Lasercutter ausschneiden.',
            'description' => '<p>Du lernst den sicheren Umgang mit dem Lasercutter, entwirfst eigene Designs am Computer und schneidest oder gravierst sie aus Holz und Acryl. Der Kurs ist ein Baustein für den Zugang zur Innovations-Werkstatt.</p>',
            'duration' => 90,
        ], $this->weekly('2026-09-23 16:00', 5, 90));

        $this->course('Löten für Anfänger', $interests, ['elektronik'], [
            'teaser' => 'Den Lötkolben sicher benutzen und die erste eigene Schaltung bauen.',
            'description' => '<p>Wie funktioniert eine Lötstation, und worauf muss ich achten? An zwei Nachmittagen lernst du die Grundlagen des Lötens und baust eine kleine Schaltung, die du mit nach Hause nehmen darfst.</p>',
            'duration' => 120,
        ], $this->weekly('2026-09-24 15:00', 2, 120));

        $this->course('Elektronische Grußkarten', $interests, ['elektronik', 'making'], [
            'teaser' => 'Grußkarten, die leuchten – mit LEDs, Kupferband und Knopfzelle.',
            'description' => '<p>Mit Kupferband, LEDs und einer Knopfzelle gestaltest du Grußkarten, die leuchten. Dabei lernst du nebenbei, wie ein Stromkreis funktioniert.</p>',
            'duration' => 60,
        ], $this->weekly('2026-09-25 14:00', 5, 60));

        $this->course('Ferien-Nähkurs', $interests, ['textil', 'umwelt'], [
            'teaser' => 'Lieblingsstücke aufwerten statt wegwerfen.',
            'description' => '<p>In diesem Ferienkurs lernst du, mit der Nähmaschine umzugehen, und wertest deine Lieblingsstücke auf, statt sie wegzuwerfen.</p>',
            'duration' => 180,
            'course_template_id' => $templates['ferien']->id,
        ], [['2026-10-28 10:00', 180]]);

        $this->course('Astronomie-Ferienkurs', $interests, ['astronomie', 'naturwissenschaften'], [
            'teaser' => 'Drei Tage Astrofotografie – den Sternenhimmel selbst fotografieren.',
            'description' => '<p>In drei Tagen lernst du die Grundlagen der Astrofotografie kennen: Wie funktioniert ein Teleskop, wie fotografiert man Mond, Planeten und Sterne, und wie bearbeitet man die Bilder danach?</p>',
            'duration' => 180,
            'course_template_id' => $templates['ferien']->id,
        ], $this->daily('2026-10-28 10:00', 3, 180));
    }

    /**
     * @return array<string, Interest>
     */
    protected function seedInterests(): array
    {
        $interests = [
            'informatik' => ['Informatik & Programmieren', '#2563eb'],
            'robotik' => ['Robotik', '#7c3aed'],
            'elektronik' => ['Elektronik', '#d97706'],
            'making' => ['Making & Werkstatt', '#ea580c'],
            'naturwissenschaften' => ['Naturwissenschaften', '#0891b2'],
            'umwelt' => ['Klima & Umwelt', '#16a34a'],
            'mathematik' => ['Mathematik', '#4f46e5'],
            'astronomie' => ['Astronomie', '#1e3a8a'],
            'textil' => ['Textil & Design', '#db2777'],
        ];

        $sort = 0;

        return collect($interests)
            ->map(fn (array $interest, string $slug): Interest => Interest::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $interest[0], 'color' => $interest[1], 'sort' => ++$sort, 'is_active' => true],
            ))
            ->all();
    }

    /**
     * @return array<string, CourseTemplate>
     */
    protected function seedTemplates(): array
    {
        $standard = CourseTemplate::query()->updateOrCreate(['name' => 'Standard'], [
            'is_default' => true,
            'card_style' => CourseCardStyle::ImageTop,
            'detail_layout' => CourseDetailLayout::HeroImage,
        ]);

        $holidays = CourseTemplate::query()->updateOrCreate(['name' => 'Ferienkurs'], [
            'card_style' => CourseCardStyle::ImageLeft,
            'detail_layout' => CourseDetailLayout::SideImage,
            'accent_color' => '#0ea5e9',
            'extra_content' => [
                ['type' => 'text', 'data' => ['body' => '<h3>Fragen zum Ferienkurs?</h3><p>Melde dich gern bei uns – wir helfen weiter.</p>']],
            ],
        ]);

        return ['standard' => $standard, 'ferien' => $holidays];
    }

    /**
     * @param  array<string, Interest>  $interests
     * @param  list<string>  $interestSlugs
     * @param  array<string, mixed>  $attributes
     * @param  list<array{0: string, 1: int}>  $dates  start and duration in minutes
     */
    protected function course(string $name, array $interests, array $interestSlugs, array $attributes, array $dates): void
    {
        $course = Course::query()->updateOrCreate(['slug' => Str::slug($name, language: 'de')], [
            'name' => $name,
            'responsible_user_id' => User::query()->oldest('id')->value('id'),
            'audience' => CourseAudience::Kids,
            'default_capacity' => 10,
            'default_waitlist_enabled' => true,
            'is_published' => true,
            ...$attributes,
        ]);

        $course->interests()->sync(collect($interestSlugs)->map(fn (string $slug): int => $interests[$slug]->id));

        $instance = CourseInstance::query()->updateOrCreate(['course_id' => $course->id, 'title' => null], [
            'capacity' => $course->default_capacity,
            'waitlist_enabled' => $course->default_waitlist_enabled,
            'status' => CourseInstanceStatus::Published,
            'location' => 'Z-LAB Bruchsal',
        ]);

        $instance->dates()->delete();

        foreach ($dates as [$start, $minutes]) {
            $startsAt = Carbon::parse($start);
            $instance->dates()->create(['starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addMinutes($minutes)]);
        }
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    protected function weekly(string $firstStart, int $count, int $minutes): array
    {
        return collect(range(0, $count - 1))
            ->map(fn (int $week): array => [Carbon::parse($firstStart)->addWeeks($week)->toDateTimeString(), $minutes])
            ->all();
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    protected function daily(string $firstStart, int $count, int $minutes): array
    {
        return collect(range(0, $count - 1))
            ->map(fn (int $day): array => [Carbon::parse($firstStart)->addDays($day)->toDateTimeString(), $minutes])
            ->all();
    }
}
