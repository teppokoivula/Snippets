<?php namespace ProcessWire;

/**
 * Snippets
 *
 * This module allows embedding snippets into page markup.
 *
 * @copyright 2021 Teppo Koivula
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License, version 2
 */
class Snippets extends WireData implements Module {

    /**
     * Name of the database table used by this module
     *
     * @var string
     */
    const TABLE_SNIPPETS = 'snippets';

    /**
     * List of all snippets
     *
     * @var array
     */
    protected $snippets = [];

    /**
     * Initialize the module, fetch dependencies, add hooks
     */
    public function init() {
        if (!class_exists('SnippetsData')) {
            require __DIR__ . '/SnippetsData.php';
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
            if (!$this->isApplicable($snippet, $event->object)) return;
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
                $applicable = $snippet->apply_to_page_list->has($page);
                break;
            case 'selector':
                $applicable = $page->is($snippet->apply_to_selector);
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
            'modified TIMESTAMP',
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
            $query = $this->database->query("SELECT apply_to, apply_to_page_list, apply_to_selector, element, element_regex, snippet, position FROM " . self::TABLE_SNIPPETS . " WHERE enabled=1");
            while ($snippet = $query->fetch(\PDO::FETCH_OBJ)) {
                $this->snippets[] = $snippet;
            }
        }
        return $this->snippets;
    }

}
