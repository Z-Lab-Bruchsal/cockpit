<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * What a tile on a section page shows. Every tile sits in the 6-column homepage grid,
 * rendered by resources/views/components/homepage/tiles/{type}.blade.php.
 */
enum TileType: string implements HasIcon, HasLabel
{
    case Section = 'section';
    case Content = 'content';
    case Courses = 'courses';
    case Offers = 'offers';
    case Text = 'text';
    case Free = 'free';
    case Image = 'image';
    case TextImage = 'text_image';
    case Button = 'button';
    case CallToAction = 'call_to_action';
    case CourseCalendar = 'course_calendar';
    case ContactForm = 'contact_form';
    case Team = 'team';
    case News = 'news';
    case Contact = 'contact';

    public function getLabel(): string
    {
        return match ($this) {
            self::Section => 'Bereich verlinken',
            self::Content => 'Inhalt',
            self::Courses => 'Kurse',
            self::Offers => 'Angebote (Durchführungen)',
            self::Text => 'Text',
            self::Free => 'Freies Schreiben',
            self::Image => 'Bild',
            self::TextImage => 'Text mit Bild',
            self::Button => 'Button',
            self::CallToAction => 'Call-to-Action',
            self::CourseCalendar => 'Kurskalender',
            self::ContactForm => 'Kontaktformular',
            self::Team => 'Team',
            self::News => 'News',
            self::Contact => 'Kontakt / Adresse',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Section => Heroicon::OutlinedArrowTopRightOnSquare,
            self::Content => Heroicon::OutlinedSquare2Stack,
            self::Courses => Heroicon::OutlinedAcademicCap,
            self::Offers => Heroicon::OutlinedRectangleGroup,
            self::Text => Heroicon::OutlinedBars3BottomLeft,
            self::Free => Heroicon::OutlinedPencilSquare,
            self::Image => Heroicon::OutlinedPhoto,
            self::TextImage => Heroicon::OutlinedNewspaper,
            self::Button => Heroicon::OutlinedCursorArrowRipple,
            self::CallToAction => Heroicon::OutlinedCursorArrowRays,
            self::CourseCalendar => Heroicon::OutlinedCalendarDays,
            self::ContactForm => Heroicon::OutlinedEnvelope,
            self::Team => Heroicon::OutlinedUserGroup,
            self::News => Heroicon::OutlinedMegaphone,
            self::Contact => Heroicon::OutlinedMapPin,
        };
    }

    /**
     * Short help shown when choosing the type in the cockpit.
     */
    public function description(): string
    {
        return match ($this) {
            self::Section => 'Navigations-Kachel zu einem anderen Bereich, übernimmt dessen Titel, Kurztext und Bild.',
            self::Content => 'Kachel mit Überschrift, Text und Bild, auf Wunsch verlinkt.',
            self::Courses => 'Je eine Kachel pro passendem Kurs, z. B. alle Kurse für Schulen oder im Bereich Informatik.',
            default => 'Inhalt über die ganze oder einen Teil der Breite.',
        };
    }

    public function defaultWidth(): int
    {
        return match ($this) {
            self::Section, self::Content => 2,
            default => 6,
        };
    }

    /**
     * Whether the tile is drawn as a card with border and background. Other tiles sit on the page directly.
     */
    public function usesCard(): bool
    {
        return in_array($this, [self::Section, self::Content], true);
    }

    /**
     * Whether the tile itself links somewhere, see LinkTarget.
     */
    public function isLinkable(): bool
    {
        return in_array($this, [self::Section, self::Content], true);
    }

    /**
     * Types whose settings are the fields of the content block of the same name, see ContentBlocks.
     */
    public function usesBlockFields(): bool
    {
        return ! in_array($this, [self::Section, self::Content, self::Courses], true);
    }
}
