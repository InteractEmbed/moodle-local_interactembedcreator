<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_interactembedcreator\local;

use context;

/**
 * Builds and installs the bundled Creator discovery course.
 *
 * @package   local_interactembedcreator
 * @copyright 2026 Michel Cardinal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class official_template {
    /** @var string Bundled illustration copied to editable projects. */
    public const ASSET_NAME = 'creator-workflow.svg';

    /**
     * Returns the localized editable document.
     *
     * @param string $projectuuid
     * @param string $name
     * @param string $lang
     * @return array
     */
    public static function document(string $projectuuid, string $name, string $lang): array {
        $sceneids = array_map(static fn() => project_repository::uuid(), range(1, 6));
        $stringmanager = get_string_manager();
        $string = static fn(string $id): string => $stringmanager->get_string(
            $id,
            'local_interactembedcreator',
            null,
            $lang
        );

        $scenes = [
            [
                'id' => $sceneids[0],
                'title' => $string('showcasescene1'),
                'style' => ['backgroundColor' => '#123b66', 'backgroundImage' => ''],
                'elements' => [
                    self::text($string('showcasetitle'), $string('showcasetitlelabel'), 160, 100, 1600, 180, 72, '#ffffff'),
                    self::text($string('showcaseintro'), $string('showcaseintrolabel'), 180, 310, 1200, 260, 54, '#dcecff'),
                    self::text($string('showcasebadge'), $string('showcasebadgelabel'), 210, 620, 1500, 120, 54, '#9fe8db'),
                    self::button(
                        $string('showcasestart'),
                        $string('showcasestartlabel'),
                        'next',
                        '',
                        710,
                        790,
                        500,
                        130,
                        '#00a88f'
                    ),
                ],
            ],
            [
                'id' => $sceneids[1],
                'title' => $string('showcasescene2'),
                'style' => ['backgroundColor' => '#f3f7fc', 'backgroundImage' => ''],
                'elements' => [
                    self::text(
                        $string('showcasestructuretitle'),
                        $string('showcasestructuretitle'),
                        180,
                        120,
                        1560,
                        180,
                        60,
                        '#123b66'
                    ),
                    self::text(
                        $string('showcasestructurebody'),
                        $string('showcasestructurelabel'),
                        360,
                        330,
                        1200,
                        390,
                        54,
                        '#172033'
                    ),
                    self::button(
                        $string('showcasenextelements'),
                        $string('showcasenextelements'),
                        'next',
                        '',
                        710,
                        790,
                        500,
                        130,
                        '#2f67b3'
                    ),
                ],
            ],
            [
                'id' => $sceneids[2],
                'title' => $string('showcasescene3'),
                'style' => ['backgroundColor' => '#eef6ff', 'backgroundImage' => ''],
                'elements' => [
                    self::text(
                        $string('showcaseelementstitle'),
                        $string('showcaseelementstitle'),
                        80,
                        90,
                        1380,
                        160,
                        60,
                        '#123b66'
                    ),
                    self::shape($string('showcasecardtextlabel'), 60, 340, 440, 440),
                    self::shape($string('showcasecardmedialabel'), 540, 340, 440, 440),
                    self::shape($string('showcasecardpathlabel'), 1020, 340, 440, 440),
                    self::text($string('showcasecardtext'), $string('showcasecardtextlabel'), 90, 400, 380, 320, 54, '#123b66'),
                    self::text($string('showcasecardmedia'), $string('showcasecardmedialabel'), 570, 400, 380, 320, 54, '#123b66'),
                    self::text($string('showcasecardpath'), $string('showcasecardpathlabel'), 1050, 400, 380, 320, 54, '#123b66'),
                ],
            ],
            [
                'id' => $sceneids[3],
                'title' => $string('showcasescene4'),
                'style' => ['backgroundColor' => '#0f4c5c', 'backgroundImage' => ''],
                'elements' => [
                    self::text($string('showcasebranchtitle'), $string('showcasebranchtitle'), 250, 100, 1420, 220, 60, '#ffffff'),
                    self::text($string('showcasebranchbody'), $string('showcasebranchlabel'), 260, 360, 1400, 280, 54, '#e7f6f5'),
                    self::button(
                        $string('showcasebranchdesign'),
                        $string('showcasebranchdesignlabel'),
                        'scene',
                        $sceneids[1],
                        160,
                        780,
                        360,
                        110,
                        '#00a88f'
                    ),
                    self::button(
                        $string('showcasebranchpublish'),
                        $string('showcasebranchpublishlabel'),
                        'scene',
                        $sceneids[5],
                        600,
                        780,
                        360,
                        110,
                        '#2f80ed'
                    ),
                    self::button(
                        $string('showcasebranchquiz'),
                        $string('showcasebranchquizlabel'),
                        'scene',
                        $sceneids[4],
                        1040,
                        780,
                        360,
                        110,
                        '#ffb000',
                        '#183044'
                    ),
                ],
            ],
            [
                'id' => $sceneids[4],
                'title' => $string('showcasescene5'),
                'style' => ['backgroundColor' => '#fff8e7', 'backgroundImage' => ''],
                'elements' => [
                    self::text($string('showcasequiztitle'), $string('showcasequiztitle'), 360, 100, 1200, 180, 60, '#7a4500'),
                    self::quiz($string, $sceneids[5], $sceneids[1]),
                ],
            ],
            [
                'id' => $sceneids[5],
                'title' => $string('showcasescene6'),
                'style' => ['backgroundColor' => '#f4f8fc', 'backgroundImage' => ''],
                'elements' => [
                    self::text($string('showcasepublishtitle'), $string('showcasepublishtitle'), 360, 70, 1200, 160, 60, '#123b66'),
                    self::text(
                        $string('showcasepublishsteps'),
                        $string('showcasepublishstepslabel'),
                        80,
                        260,
                        720,
                        520,
                        48,
                        '#172033'
                    ),
                    self::image($string('showcaseworkflowalt'), 840, 280, 840, 400),
                    self::button(
                        $string('showcaserestart'),
                        $string('showcaserestartlabel'),
                        'scene',
                        $sceneids[0],
                        710,
                        820,
                        500,
                        120,
                        '#1769aa'
                    ),
                ],
            ],
        ];

        return [
            'schema' => 'interactembed-project',
            'version' => 1,
            'projectId' => $projectuuid,
            'metadata' => ['title' => $name],
            'canvas' => ['width' => 1920, 'height' => 1080, 'aspectRatio' => '16:9'],
            'settings' => [
                'navigation' => true,
                'completion' => 'last-scene',
                'locale' => $lang,
            ],
            'scenes' => $scenes,
        ];
    }

    /**
     * Copies bundled assets into an editable project's Moodle file area.
     *
     * @param context $context
     * @param int $projectid
     * @param int $userid
     */
    public static function install_assets(context $context, int $projectid, int $userid): void {
        $source = __DIR__ . '/../../pix/' . self::ASSET_NAME;
        $record = [
            'contextid' => $context->id,
            'component' => 'local_interactembedcreator',
            'filearea' => 'asset',
            'itemid' => $projectid,
            'filepath' => '/',
            'filename' => self::ASSET_NAME,
            'userid' => $userid,
        ];
        get_file_storage()->create_file_from_pathname($record, $source);
    }

    /**
     * Creates a text element.
     *
     * @return array
     */
    private static function text(
        string $text,
        string $label,
        int $x,
        int $y,
        int $width,
        int $height,
        int $fontsize,
        string $color
    ): array {
        return [
            'id' => project_repository::uuid(), 'type' => 'text',
            'transform' => compact('x', 'y', 'width', 'height') + ['rotation' => 0],
            'content' => ['text' => $text],
            'style' => ['fontSize' => $fontsize, 'color' => $color, 'textAlign' => 'center'],
            'accessibility' => ['label' => $label],
        ];
    }

    /**
     * Creates a navigation button element.
     *
     * @return array
     */
    private static function button(
        string $text,
        string $label,
        string $action,
        string $targetsceneid,
        int $x,
        int $y,
        int $width,
        int $height,
        string $background,
        string $color = '#ffffff'
    ): array {
        return [
            'id' => project_repository::uuid(), 'type' => 'button',
            'transform' => compact('x', 'y', 'width', 'height') + ['rotation' => 0],
            'content' => ['text' => $text, 'action' => $action, 'targetSceneId' => $targetsceneid, 'href' => ''],
            'style' => ['fontSize' => 40, 'color' => $color, 'background' => $background,
                'textAlign' => 'center', 'borderRadius' => 18],
            'accessibility' => ['label' => $label],
        ];
    }

    /**
     * Creates a shape element.
     *
     * @return array
     */
    private static function shape(string $label, int $x, int $y, int $width, int $height): array {
        return [
            'id' => project_repository::uuid(), 'type' => 'shape',
            'transform' => compact('x', 'y', 'width', 'height') + ['rotation' => 0],
            'content' => [], 'style' => ['background' => '#cfe0f7', 'borderRadius' => 44],
            'accessibility' => ['label' => $label],
        ];
    }

    /**
     * Creates an image element.
     *
     * @return array
     */
    private static function image(string $alt, int $x, int $y, int $width, int $height): array {
        return [
            'id' => project_repository::uuid(), 'type' => 'image',
            'transform' => compact('x', 'y', 'width', 'height') + ['rotation' => 0],
            'content' => ['assetPath' => self::ASSET_NAME, 'fit' => 'contain'],
            'style' => [], 'accessibility' => ['alt' => $alt, 'label' => $alt, 'decorative' => false],
        ];
    }

    /**
     * Creates the demonstration quiz element.
     *
     * @return array
     */
    private static function quiz(callable $string, string $correctsceneid, string $incorrectsceneid): array {
        return [
            'id' => project_repository::uuid(), 'type' => 'quiz',
            'transform' => ['x' => 360, 'y' => 300, 'width' => 1200, 'height' => 580, 'rotation' => 0],
            'content' => [
                'question' => $string('showcasequizquestion'),
                'answers' => [$string('showcasequizanswer1'), $string('showcasequizanswer2'),
                    $string('showcasequizanswer3'), $string('showcasequizanswer4')],
                'selectionMode' => 'multiple', 'correctIndex' => 0, 'correctIndexes' => [0, 1, 3], 'points' => 5,
                'checkLabel' => $string('showcasequizcheck'),
                'correctFeedback' => $string('showcasequizcorrect'),
                'incorrectFeedback' => $string('showcasequizincorrect'),
                'correctSceneId' => $correctsceneid, 'incorrectSceneId' => $incorrectsceneid,
            ],
            'style' => ['background' => '#ffffff', 'color' => '#172033'],
            'accessibility' => ['label' => $string('showcasequizlabel')],
        ];
    }
}
