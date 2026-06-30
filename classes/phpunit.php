<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plugin version and other meta-data are defined here.
 *
 * @package     tool_phpunitchecker
 * @copyright   2026 MoodleMootDACH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_phpunitchecker;

use core\test\phpunit\phpunit_util;

class phpunit {

    /**
     * Instance of the singleton.
     * @var phpunit
     */
    static $instance;

    /**
     * Full path to the phpunit binary.
     * 
     * @var string
     */
    private $bin;

    /**
     * Location of the php binary.
     * @var string
     */
    private $php;

    /**
     * Diretory of the phpunit cli dir in Moodle.
     * @var string
     */
    private $moodlephpunitcli;

    /**
     * Output of last command.
     * @var string[]
     */
    private $output;

    /**
     * Exec code of last command.
     * @var int
     */
    private $code;

    /**
     * Constructor.
     */
    public function __construct() {
        global $CFG;

        $this->bin = implode(DIRECTORY_SEPARATOR, [$CFG->root, 'vendor', 'bin', 'phpunit']);
        $this->output = [];
        $this->code = 0;
        $this->moodlephpunitcli = $CFG->dirroot . implode(DIRECTORY_SEPARATOR, ['', 'admin', 'tool', 'phpunit', 'cli']);
        $this->php = $CFG->pathtophp ?? 'php';
    }

    /**
     * Get the singleton instance.
     */
    public static function get_instance(): self {
        if (static::$instance === null) {
            static::$instance = new self();
        }
        return static::$instance;
    }

    /**
     * Returns an array <string,int> with key the suite name and value the number of test cases.
     * @return array
     */
    public function list_suites(): array {
        $this->exec($this->bin, ['--list-suites' => null]);
        if ($this->code !== 0) {
            return [];
        }
        $suites = [];
        foreach ($this->output as $line) {
            $line = trim($line);
            if (substr($line, 0, 2) === '- ') {
                $parts = explode(' ', substr($line, 2));
                $suite = array_shift($parts);
                if (preg_match('(\d+)', implode(' ', $parts), $matches)) {
                    $suites[$suite] = (int)$matches[0];
                } else {
                    $suites[$suite] = -1;
                }
            }
        }
        return $suites;
    }

    /**
     * Check if php unit is set up.
     * @return bool
     */
    public function is_ready(): bool {
        $this->exec("{$this->php} {$this->moodlephpunitcli}/util.php --diag");
        return $this->code === 0;
    }

    /**
     * Setup the phpunit tesusite.
     * @return bool
     */
    public function make_ready(): bool {
        $this->exec("{$this->php} {$this->moodlephpunitcli}/init.php");
        return $this->code === 0;
    }

    /**
     * Get the output from the last command.
     * @return string
     */
    public function get_output(): string {
        return implode(PHP_EOL, $this->output);
    }

    /**
     * Execute a command. The output and result code is stored in member variables.
     * @param string $cmd
     * @param array $args
     */
    protected function exec(string $cmd, ?array $args = []): void {
        global $CFG;

        foreach ($args as $arg => $val) {
            $cmd .= empty($val)
                ? ' ' . $arg
                : ' ' . $arg . ' ' . escapeshellarg($val);
        }
        // Composer needs a writable HOME/COMPOSER_HOME; the web SAPI usually has neither.
        $composerhome = $CFG->dataroot . '/composer_home';
        if (!is_dir($composerhome)) {
            make_writable_directory($composerhome);
        }
        // Environment variables required for the init.php to initialize the test environment.
        $env = array_merge($_ENV, getenv(), [
            'HOME'          => $composerhome,
            'COMPOSER_HOME' => $composerhome,
            'PATH'          => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        ]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // phpunit.xml and vendor/bin/phpunit live at $CFG->root (the dir above public/),
        // and phpunit reads phpunit.xml relative to its working directory.
        $process = proc_open($cmd, $descriptors, $pipes, $CFG->root, $env);
        if (!is_resource($process)) {
            $this->code = -1;
            $this->output = ["Failed to start: $cmd"];
            return;
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $this->code   = proc_close($process); // Blocks until the child fully exits.
        $this->output = explode("\n", rtrim($stdout . $stderr));
    }
}