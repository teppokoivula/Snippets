<?php namespace ProcessWire;

/**
 * Snippets
 *
 * This module allows embedding snippets into page markup.
 *
 * @copyright 2021 Teppo Koivula
 * @license https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class Snippets extends WireData implements Module, ConfigurableModule {

    /**
     * Name of the database table used by this module
     *
     * @var string
     */
    const TABLE_SNIPPETS = 'snippets';

    /**
     * Schema version for database table used by this module
     *
     * @var int
     */
    const SCHEMA_VERSION = 2;

    /**
     * List of all snippets
     *
     * @var array
     */
    protected $snippets = [];

    /**
     * Default configuration for this module
     *
     * @return array
     */
    protected function getDefaultData(): array {
        return [
            'schema_version' => 1,
        ];
    }

    /**
     * Populate the default config data
     */
    public function __construct() {
        foreach ($this->getDefaultData() as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Get module config inputfields
     *
     * @param InputfieldWrapper $inputfields
     */
    public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {}

    /**
     * Initialize the module
     */
    public function init() {
        if (!class_exists('SnippetsData')) {
            require __DIR__ . '/SnippetsData.php';
        }
        if ($this->schema_version < self::SCHEMA_VERSION) {
            $this->updateDatabaseSchema();
        }
        $this->addHookAfter('Page::render', $this, 'hookPageRender');
    }

    /**
     * Process snippets
     *
     * @param HookEvent $event
     */
    protected function hookPageRender(HookEvent $event) {

        // bail out early if there's no content
        if (empty($event->return)) return;

        // find snippets and process them one by one
        $snippets = $this->getSnippets();
        foreach ($snippets as $snippet) {
            if (!$this->isApplicable($snippet, $event->object)) continue;
            $event->return = $this->applySnippet(
                $snippet,
                $event->return,
                new SnippetsData([
                    'page' => $event->object,
                ]),
                [
                    'tagOpen' => '{{',
                    'tagClose' => '}}',
                ]
            );
        }
    }

    /**
     * Check if snippet is applicable
     *
     * @param object $snippet
     * @param Page $page
     * @return bool
     */
    public function ___isApplicable(object $snippet, Page $page): bool {
        $applicable = false;
        switch ($snippet->apply_to) {
            case 'all':
                $applicable = true;
                break;
            case 'admin':
                $applicable = $page->is('template=admin');
                break;
            case 'non_admin':
                $applicable = $page->is('template!=admin');
                break;
            case 'page_list':
                $applicable = $snippet->apply_to_page_list && $page->is('id=' . $snippet->apply_to_page_list);
                break;
            case 'selector':
                $applicable = $snippet->apply_to_selector && $page->is($snippet->apply_to_selector);
                break;
        }
        return $applicable;
    }

    /**
     * Apply snippet to page content
     *
     * @param object $snippet
     * @param string $content
     * @param object $vars
     * @param array $options
     * @return string
     */
    public function ___applySnippet(object $snippet, string $content, object $vars, array $options = []): string {
        return preg_replace(
            $snippet->element == 'other' ? $snippet->element_regex : $snippet->element,
            ($snippet->position == 'after' ? '$0' : '') . wirePopulateStringTags($snippet->snippet, $vars, $options) . ($snippet->position == 'before' ? '$0' : ''),
            $content,
            1
        );
    }

    /**
     * When module is installed, create database table for storing snippets
     */
    public function install() {
        $this->createTable(self::TABLE_SNIPPETS, array(
            'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY',
            'label VARCHAR(128) NOT NULL',
            'summary VARCHAR(255)',
            'snippet TEXT NOT NULL',
            'position VARCHAR(128) NOT NULL',
            'element VARCHAR(255) NOT NULL',
            'element_regex VARCHAR(255)',
            'apply_to VARCHAR(128)',
            'apply_to_selector TEXT',
            'apply_to_page_list TEXT',
            'enabled BOOLEAN NOT NULL DEFAULT 0',
            'created TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'created_users_id INT UNSIGNED',
            'modified TIMESTAMP NULL',
            'modified_users_id INT UNSIGNED',
        ));
    }

    /**
     * When module is uninstalled, drop database table created during install
     */
    public function uninstall() {
        $this->dropTable(self::TABLE_SNIPPETS);
    }
    
    /**
     * Helper method for dropping tables
     *
     * @param string $table Table name
     */
    protected function dropTable($table) {
        $table = $this->database->escapeStr($table);
        $stmt = $this->database->prepare("SHOW TABLES LIKE '$table'");
        $stmt->execute();
        if (count($stmt->fetchAll()) == 1) {
            $this->database->query("DROP TABLE $table");
            $this->message("Dropped Table: $table");
        }
    }

    /**
     * Helper method for creating tables
     *
     * @param string $table Table name
     * @param array $schema Table schema
     * @throws WireDatabaseException if table already exists
     */
    protected function createTable($table, $schema) {
        $table = $this->database->escapeStr($table);
        $stmt = $this->database->prepare("SHOW TABLES LIKE '$table'");
        $stmt->execute();
        if (count($stmt->fetchAll()) == 1) {
            throw new WireDatabaseException("Table $table already exists");
        }
        $sql = "CREATE TABLE $table (";
        $sql .= implode(', ', $schema);
        $sql .= ") ENGINE = MYISAM DEFAULT CHARSET=utf8";
        $this->database->query($sql);
        $this->message("Created Table: $table");
    }

    /**
     * Fetch all snippets from database
     *
     * @return array
     */
    public function getSnippets(): array {
        if (empty($this->snippets)) {
            $query = $this->database->query("SELECT apply_to, apply_to_page_list, apply_to_selector, element, element_regex, snippet, position, sort FROM " . self::TABLE_SNIPPETS . " WHERE enabled=1 ORDER BY sort, id");
            while ($snippet = $query->fetch(\PDO::FETCH_OBJ)) {
                if ($snippet->apply_to_page_list) {
                    // apply basic validation to the apply to page list property
                    $apply_to_page_list = explode(',', $snippet->apply_to_page_list);
                    $apply_to_page_list = array_filter($apply_to_page_list, function($page_id) {
                        return $page_id > 0 && (int) $page_id == $page_id;
                    });
                    $snippet->apply_to_page_list = implode('|', $apply_to_page_list);
                }
                $this->snippets[] = $snippet;
            }
        }
        return $this->snippets;
    }

    /**
     * Update database schema
     *
     * This method applies incremental updates until latest schema version is reached, while also keeping schema_version
     * config setting up to date.
     *
     * @throws WireException if database schema version isn't recognized
     */
    protected function updateDatabaseSchema() {
        while ($this->schema_version < self::SCHEMA_VERSION) {

            // increment; defaults to 1, but in some cases we may be able to skip over a specific schema update
            $increment = 1;

            // first we need to figure out which update we're going to trigger
            switch ($this->schema_version) {
                case 1:
                    // update #1: add sort column
                    $sql = [
                        "ALTER TABLE `" . self::TABLE_SNIPPETS . "` ADD `sort` INT(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `modified_users_id`",
                    ];
                    break;
                default:
                    throw new WireException("Unrecognized database schema version: {$this->schema_version}");
            }

            // we're ready to execute this update
            foreach ($sql as $sql_query) {
                $schema_updated = $this->executeDatabaseSchemaUpdate($sql_query);
                if (!$schema_updated) {
                    break;
                }
            }

            // if update fails: log, show error message (if current user is superuser) and continue
            if (!$schema_updated) {
                $this->error(sprintf(
                    $this->_("Running database schema update %d failed"),
                    $this->schema_version
                ), $this->user->isSuperuser() ? Notice::log : Notice::logOnly);
                return;
            }

            // after successful update increment schema version and display a message if current user is superuser
            $this->schema_version += $increment;
            $configData = $this->modules->getModuleConfigData($this);
            $configData['schema_version'] = $this->schema_version;
            $this->modules->saveModuleConfigData($this, $configData);
            if ($this->user->isSuperuser()) {
                $this->message(sprintf(
                    $this->_('Snippets database schema update applied (#%d).'),
                    $this->schema_version - 1
                ));
            }
        }
    }

    /**
     * Execute database schema update
     *
     * @param string $sql
     * @return bool
     */
    protected function executeDatabaseSchemaUpdate($sql): bool {
        try {
            $updated_rows = $this->database->exec($sql);
            return $updated_rows !== false;
        } catch (\PDOException $e) {
            if (isset($e->errorInfo[1]) && in_array($e->errorInfo[1], [1060, 1061, 1091])) {
                // 1060 (column already exists), 1061 (duplicate key name), and 1091 (can't drop index) are errors that
                // can be safely ignored here; the most likely issue would be that this update has already been applied
                return true;
            }
            // another type of error; log, show error message (if current user is superuser) and return false
            $this->error(sprintf(
                'Error updating database schema: %s (%s)',
                $e->getMessage(),
                $e->getCode()
            ), $this->user->isSuperuser() ? Notice::log : Notice::logOnly);
            return false;
        }
    }

}
