<?php
// This file is part of Moodle - http://moodle.org/.

namespace local_interactembedcreator\local;

use context;
use context_course;
use context_coursecat;
use context_system;
use moodle_exception;
use stdClass;

/** Resolves and describes the supported InteractEmbed library contexts. */
final class library_context {
    /** Returns a supported context from the current request. */
    public static function from_request(): context {
        $contextid = optional_param('contextid', 0, PARAM_INT);
        $courseid = optional_param('courseid', 0, PARAM_INT);
        if ($contextid) {
            $context = context::instance_by_id($contextid, MUST_EXIST);
        } else if ($courseid) {
            $context = context_course::instance($courseid);
        } else {
            $context = context_system::instance();
        }
        self::validate($context);
        return $context;
    }

    /** Rejects contexts which cannot own a Creator library. */
    public static function validate(context $context): void {
        if (!$context instanceof context_system && !$context instanceof context_coursecat &&
                !$context instanceof context_course) {
            throw new moodle_exception('invalidcontext');
        }
    }

    /** Applies the appropriate login requirement for a supported context. */
    public static function require_login(context $context): void {
        self::validate($context);
        if ($context instanceof context_course) {
            require_login(get_course($context->instanceid));
            return;
        }
        require_login();
    }

    /** Returns the course object Moodle needs while rendering a contextual page. */
    public static function page_course(context $context): stdClass {
        return get_course($context instanceof context_course ? $context->instanceid : SITEID);
    }

    /** Returns canonical URL parameters for a contextual library page. */
    public static function url_params(context $context): array {
        return ['contextid' => $context->id];
    }

    /** Returns a human-readable context label. */
    public static function label(context $context): string {
        if ($context instanceof context_system) {
            return get_string('sitelibrary', 'local_interactembedcreator');
        }
        if ($context instanceof context_coursecat) {
            $category = \core_course_category::get($context->instanceid, MUST_EXIST, true);
            return get_string('categoryscope', 'local_interactembedcreator', format_string($category->name));
        }
        return get_string('coursescope', 'local_interactembedcreator',
            format_string(get_course($context->instanceid)->fullname));
    }

    /**
     * Lists contexts in which the user has a capability.
     *
     * @return context[] Contexts keyed by context id.
     */
    public static function available(int $userid, string $capability): array {
        global $DB;

        $contexts = [];
        $systemcontext = context_system::instance();
        if (has_capability($capability, $systemcontext, $userid)) {
            $contexts[$systemcontext->id] = $systemcontext;
        }
        foreach ($DB->get_records('course_categories', null, 'sortorder', 'id,visible') as $category) {
            $context = context_coursecat::instance($category->id);
            if (has_capability($capability, $context, $userid) &&
                    ($category->visible || has_capability('moodle/category:viewhiddencategories', $context, $userid))) {
                $contexts[$context->id] = $context;
            }
        }
        $courses = get_user_capability_course($capability, $userid, true, 'fullname,visible') ?: [];
        foreach ($courses as $course) {
            if ((int) $course->id === SITEID) {
                continue;
            }
            $context = context_course::instance($course->id);
            if ($course->visible || has_capability('moodle/course:viewhiddencourses', $context, $userid)) {
                $contexts[$context->id] = $context;
            }
        }
        return $contexts;
    }

    /** Returns the contexts whose publications are inherited by a course. */
    public static function publication_context_ids(context_course $context): array {
        $ids = array_merge([$context->id], $context->get_parent_context_ids());
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
