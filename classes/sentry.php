<?php
// This file is part of Moodle - https://moodle.org/
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

/**
 * Sentry SDK bootstrap for quizaccess_proview.
 *
 * @package    quizaccess_proview
 * @copyright  2026 Talview Inc.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_proview;

/**
 * Thin wrapper around the Sentry PHP SDK.
 *
 * Initialises the SDK once per request and exposes helpers for exception
 * capture and performance span creation. All methods are no-ops when the
 * vendor autoloader is absent so the plugin works in environments where
 * Sentry is not bundled.
 */
class sentry {
    /** @var string Sentry DSN for the quizaccess_proview project. */
    const DSN = 'https://070e04ad3039bad6c35fe0ee09672aed@sentry.talview.org/175';

    /** @var bool Whether the SDK has been successfully initialised. */
    private static bool $initialized = false;

    /**
     * Initialise the Sentry SDK.
     *
     * Safe to call multiple times — subsequent calls are no-ops.
     * Silently skips if vendor/autoload.php is not present.
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }

        $autoloader = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoloader)) {
            return;
        }

        require_once($autoloader);

        if (!class_exists(\Sentry\State\Hub::class)) {
            return;
        }

        global $CFG, $USER;

        $pluginman = \core_plugin_manager::instance();
        $info      = $pluginman->get_plugin_info('quizaccess_proview');
        $release   = $info ? 'quizaccess_proview@' . $info->release : 'quizaccess_proview@unknown';

        \Sentry\init([
            'dsn'                => self::DSN,
            'release'            => $release,
            'traces_sample_rate' => 1.0,
            'send_default_pii'   => false,
        ]);

        \Sentry\configureScope(function (\Sentry\State\Scope $scope) use ($CFG, $USER): void {
            $scope->setTag('moodle_version', (string) ($CFG->version ?? 'unknown'));
            $scope->setTag('moodle_release', (string) ($CFG->release ?? 'unknown'));

            if (!empty($USER->id)) {
                $scope->setUser(['id' => (string) $USER->id]);
            }
        });

        self::$initialized = true;
    }

    /**
     * Capture a throwable and send it to Sentry.
     *
     * No-op when SDK is not initialised.
     *
     * @param \Throwable $e The exception or error to capture.
     */
    public static function capture_exception(\Throwable $e): void {
        if (!self::$initialized) {
            return;
        }
        \Sentry\captureException($e);
    }

    /**
     * Start a child span on the current Sentry transaction.
     *
     * Returns null when SDK is not initialised or no active transaction exists.
     *
     * @param string $op          Span operation name (e.g. 'http.client', 'cache.get').
     * @param string $description Human-readable description (e.g. 'GET /proview/token').
     * @return \Sentry\Tracing\Span|null The started span, or null.
     */
    public static function start_span(string $op, string $description): ?\Sentry\Tracing\Span {
        if (!self::$initialized) {
            return null;
        }

        $parent = \Sentry\SentrySdk::getCurrentHub()->getSpan();
        if ($parent === null) {
            return null;
        }

        $context = \Sentry\Tracing\SpanContext::make()
            ->setOp($op)
            ->setDescription($description);

        return $parent->startChild($context);
    }

    /**
     * Whether the SDK is currently active.
     *
     * @return bool
     */
    public static function is_active(): bool {
        return self::$initialized;
    }
}
