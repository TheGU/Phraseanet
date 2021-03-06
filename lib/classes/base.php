<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2015 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Alchemy\Phrasea\Application;
use Alchemy\Phrasea\Core\Version as PhraseaVersion;
use vierbergenlars\SemVer\version;
use Doctrine\DBAL\Connection;

abstract class base implements cache_cacheableInterface
{
    protected $version;

    /** @var int */
    protected $id;
    protected $schema;
    protected $dbname;
    protected $passwd;
    protected $user;
    protected $port;
    protected $host;

    /** @var Connection */
    protected $connection;
    /** @var Application */
    protected $app;

    const APPLICATION_BOX = 'APPLICATION_BOX';
    const DATA_BOX = 'DATA_BOX';

    public function __construct(Application $application, Connection $connection)
    {
        $this->app = $application;
        $this->connection = $connection;
    }

    abstract public function get_base_type();

    public function get_schema()
    {
        if ($this->schema) {
            return $this->schema;
        }

        $this->load_schema();

        return $this->schema;
    }

    public function get_dbname()
    {
        return $this->dbname;
    }

    public function get_passwd()
    {
        return $this->passwd;
    }

    public function get_user()
    {
        return $this->user;
    }

    public function get_port()
    {
        return $this->port;
    }

    public function get_host()
    {
        return $this->host;
    }

    /** @return Connection */
    public function get_connection()
    {
        return $this->connection;
    }

    public function get_cache()
    {
        return $this->app['cache'];
    }

    public function get_data_from_cache($option = null)
    {
        if ($this->get_base_type() == self::DATA_BOX) {
            \cache_databox::refresh($this->app, $this->id);
        }

        $data = $this->get_cache()->get($this->get_cache_key($option));

        if (is_object($data) && method_exists($data, 'hydrate')) {
            $data->hydrate($this->app);
        }

        return $data;
    }

    public function set_data_to_cache($value, $option = null, $duration = 0)
    {
        return $this->get_cache()->save($this->get_cache_key($option), $value, $duration);
    }

    public function delete_data_from_cache($option = null)
    {
        $appbox = $this->get_base_type() == self::APPLICATION_BOX ? $this : $this->get_appbox();
        if ($option === appbox::CACHE_LIST_BASES) {
            $keys = [$this->get_cache_key(appbox::CACHE_LIST_BASES)];
            phrasea::reset_sbasDatas($appbox);
            phrasea::reset_baseDatas($appbox);
            phrasea::clear_sbas_params($this->app);
            $keys[] = $this->get_cache_key(appbox::CACHE_SBAS_IDS);

            return $this->get_cache()->deleteMulti($keys);
        }

        if (is_array($option)) {
            foreach ($option as $key => $value)
                $option[$key] = $this->get_cache_key($value);

            return $this->get_cache()->deleteMulti($option);
        } else {
            return $this->get_cache()->delete($this->get_cache_key($option));
        }
    }

    public function get_cache_key($option = null)
    {
        throw new Exception(__METHOD__ . ' must be defined in extended class');
    }

    public function get_version()
    {
        if ($this->version) {
            return $this->version;
        }

        $version = '0.0.0';

        $sql = '';
        if ($this->get_base_type() == self::APPLICATION_BOX)
            $sql = 'SELECT version FROM sitepreff';
        if ($this->get_base_type() == self::DATA_BOX)
            $sql = 'SELECT value AS version FROM pref WHERE prop="version" LIMIT 1;';

        if ($sql !== '') {
            $stmt = $this->get_connection()->prepare($sql);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if ($row)
                $version = $row['version'];
        }

        $this->version = $version;

        return $this->version;
    }

    protected function upgradeDb($apply_patches, Application $app)
    {
        $recommends = [];

        $allTables = [];

        $schema = $this->get_schema();

        foreach ($schema->tables->table as $table) {
            $allTables[(string) $table['name']] = $table;
        }

        $sql = "SHOW TABLE STATUS";
        $stmt = $this->get_connection()->prepare($sql);
        $stmt->execute();
        $rs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $ORMTables = [
            'AuthFailures',
            'ApiApplications',
            'ApiAccounts',
            'ApiLogs',
            'ApiOauthCodes',
            'ApiOauthRefreshTokens',
            'ApiOauthTokens',
            'AggregateTokens',
            'BasketElements',
            'Baskets',
            'FeedEntries',
            'FeedItems',
            'FeedPublishers',
            'FeedTokens',
            'Feeds',
            'FtpCredential',
            'FtpExportElements',
            'FtpExports',
            'OrderElements',
            'Orders',
            'Registrations',
            'StoryWZ',
            'UsrListOwners',
            'UsrLists',
            'UsrListsContent',
            'ValidationDatas',
            'ValidationParticipants',
            'ValidationSessions',
            'LazaretAttributes',
            'LazaretChecks',
            'LazaretFiles',
            'LazaretSessions',
            'SessionModules',
            'Sessions',
            'Tasks',
            'UsrAuthProviders',
            'UserQueries',
            'UserSettings',
            'Users',
            'UserNotificationSettings',
        ];

        foreach ($rs as $row) {
            $tname = $row["Name"];
            if (isset($allTables[$tname])) {
                $engine = strtolower(trim($allTables[$tname]->engine));
                $ref_engine = strtolower($row['Engine']);

                if ($engine != $ref_engine && in_array($engine, ['innodb', 'myisam'])) {
                    $sql = 'ALTER TABLE `' . $tname . '` ENGINE = ' . $engine;
                    try {
                        $stmt = $this->get_connection()->prepare($sql);
                        $stmt->execute();
                        $stmt->closeCursor();
                    } catch (\Exception $e) {
                        $recommends[] = [
                            'message' => $app->trans('Erreur lors de la tentative ; errreur : %message%', ['%message%' => $e->getMessage()]),
                            'sql'     => $sql
                        ];
                    }
                }

                $ret = self::upgradeTable($allTables[$tname]);
                $recommends = array_merge($recommends, $ret);
                unset($allTables[$tname]);
            } elseif ( ! in_array($tname, $ORMTables)) {
                $recommends[] = [
                    'message' => 'Une table pourrait etre supprime',
                    'sql'     => 'DROP TABLE ' . $this->dbname . '.`' . $tname . '`;'
                ];
            }
        }

        foreach ($allTables as $tname => $table) {
            $this->createTable($table);
        }
        $current_version = $this->get_version();

        if ($apply_patches) {
            $this->apply_patches($current_version, $app['phraseanet.version']->getNumber(), false, $app);
        }

        return $recommends;
    }

    protected function setVersion(PhraseaVersion $version)
    {
        try {
            $sql = '';
            if ($this->get_base_type() === self::APPLICATION_BOX)
                $sql = 'UPDATE sitepreff SET version = :version WHERE id = 1';
            if ($this->get_base_type() === self::DATA_BOX) {
                $sql = 'DELETE FROM pref WHERE prop="version"';
                $this->get_connection()->query($sql);
                $sql = 'INSERT INTO pref (prop, value, locale, updated_on) VALUES ("version", :version, "", NOW())';
            }
            if ($sql !== '') {
                $stmt = $this->get_connection()->prepare($sql);
                $stmt->execute([':version' => $version->getNumber()]);
                $stmt->closeCursor();

                $this->version = $version->getNumber();

                return true;
            }
        } catch (\Exception $e) {
            throw new Exception('Unable to set the database version : '.$e->getMessage());
        }

        return;
    }

    /**
     * @return base
     */
    protected function load_schema()
    {
        if ($this->schema) {
            return $this;
        }

        if (false === $structure = simplexml_load_file(__DIR__ . "/../../lib/conf.d/bases_structure.xml")) {
            throw new Exception('Unable to load schema');
        }

        if ($this->get_base_type() === self::APPLICATION_BOX)
            $this->schema = $structure->appbox;
        elseif ($this->get_base_type() === self::DATA_BOX)
            $this->schema = $structure->databox;
        else
            throw new Exception('Unknown schema type');

        return $this;
    }

    /**
     * @return base
     */
    public function insert_datas()
    {
        $this->load_schema();

        foreach ($this->get_schema()->tables->table as $table) {
            $this->createTable($table);
        }

        $this->setVersion($this->app['phraseanet.version']);

        return $this;
    }

    /**
     * @param  SimpleXMLElement $table
     * @return base
     */
    protected function createTable(SimpleXMLElement $table)
    {
        $field_stmt = $defaults_stmt = [];

        $create_stmt = "CREATE TABLE IF NOT EXISTS `" . $table['name'] . "` (";

        foreach ($table->fields->field as $field) {
            $isnull = trim($field->null) == "" ? "NOT NULL" : "NULL";

            if (trim($field->default) != "" && trim($field->default) != "CURRENT_TIMESTAMP")
                $is_default = " default '" . $field->default . "'";
            elseif (trim($field->default) == "CURRENT_TIMESTAMP")
                $is_default = " default " . $field->default;
            else
                $is_default = '';

            $character_set = '';
            if (in_array(strtolower((string) $field->type), ['text', 'longtext', 'mediumtext', 'tinytext'])
                || substr(strtolower((string) $field->type), 0, 7) == 'varchar'
                || in_array(substr(strtolower((string) $field->type), 0, 4), ['char', 'enum'])) {

                $collation = trim((string) $field->collation) != '' ? trim((string) $field->collation) : 'utf8_unicode_ci';

                $collations = array_reverse(explode('_', $collation));
                $code = array_pop($collations);

                $character_set = ' CHARACTER SET ' . $code . ' COLLATE ' . $collation;
            }

            $field_stmt[] = " `" . $field->name . "` " . $field->type . " "
                . $field->extra . " " . $character_set . " "
                . $is_default . " " . $isnull . "";
        }

        if ($table->indexes) {
            foreach ($table->indexes->index as $index) {
                switch ($index->type) {
                    case "PRIMARY":
                        $primary_fields = [];

                        foreach ($index->fields->field as $field) {
                            $primary_fields[] = "`" . $field . "`";
                        }

                        $field_stmt[] = 'PRIMARY KEY (' . implode(',', $primary_fields) . ')';
                        break;
                    case "UNIQUE":
                        $unique_fields = [];

                        foreach ($index->fields->field as $field) {
                            $unique_fields[] = "`" . $field . "`";
                        }

                        $field_stmt[] = 'UNIQUE KEY `' . $index->name . '` (' . implode(',', $unique_fields) . ')';
                        break;
                    case "INDEX":
                        $index_fields = [];

                        foreach ($index->fields->field as $field) {
                            $index_fields[] = "`" . $field . "`";
                        }

                        $field_stmt[] = 'KEY `' . $index->name . '` (' . implode(',', $index_fields) . ')';
                        break;
                }
            }
        }
        if ($table->defaults) {
            foreach ($table->defaults->default as $default) {
                $k = $v = $params = $dates_values = [];
                $nonce = $this->app['random.medium']->generateString(16);
                foreach ($default->data as $data) {
                    $k = trim($data['key']);
                    if ($k === 'usr_password')
                        $data = $this->app['auth.password-encoder']->encodePassword($data, $nonce);
                    if ($k === 'nonce')
                        $data = $nonce;
                    $v = trim(str_replace(["\r\n", "\r", "\n", "\t"], '', $data));

                    if (trim(mb_strtolower($v)) == 'now()')
                        $dates_values [$k] = 'NOW()';
                    else
                        $params[$k] = (trim(mb_strtolower($v)) == 'null' ? null : $v);
                }

                $separator = ((count($params) > 0 && count($dates_values) > 0) ? ', ' : '');

                $defaults_stmt[] = [
                    'sql'    =>
                    'INSERT INTO `' . $table['name'] . '` (' . implode(', ', array_keys($params))
                    . $separator . implode(', ', array_keys($dates_values)) . ')
                      VALUES (:' . implode(', :', array_keys($params))
                    . $separator . implode(', ', array_values($dates_values)) . ') '
                    , 'params' => $params
                ];
            }
        }

        $engine = mb_strtolower(trim($table->engine));
        $engine = in_array($engine, ['innodb', 'myisam']) ? $engine : 'innodb';

        $create_stmt .= implode(',', $field_stmt);
        $create_stmt .= ") ENGINE=" . $engine . " CHARACTER SET utf8 COLLATE utf8_unicode_ci;";

        $stmt = $this->get_connection()->prepare($create_stmt);
        $stmt->execute();
        $stmt->closeCursor();

        foreach ($defaults_stmt as $def) {
            $stmt = $this->get_connection()->prepare($def['sql']);
            $stmt->execute($def['params']);
            $stmt->closeCursor();
        }
        unset($stmt);

        return $this;
    }

    protected function upgradeTable(SimpleXMLElement $table)
    {
        $correct_table = ['fields' => [], 'indexes' => [], 'collation' => []];
        $alter = $alter_pre = $return = [];

        foreach ($table->fields->field as $field) {
            $expr = trim((string) $field->type);

            $_extra = trim((string) $field->extra);
            if ($_extra)
                $expr .= ' ' . $_extra;

            $collation = trim((string) $field->collation) != '' ? trim((string) $field->collation) : 'utf8_unicode_ci';

            if (in_array(strtolower((string) $field->type), ['text', 'longtext', 'mediumtext', 'tinytext'])
                || substr(strtolower((string) $field->type), 0, 7) == 'varchar'
                || in_array(substr(strtolower((string) $field->type), 0, 4), ['char', 'enum'])) {
                $collations = array_reverse(explode('_', $collation));
                $code = array_pop($collations);

                $collation = ' CHARACTER SET ' . $code . ' COLLATE ' . $collation;

                $correct_table['collation'][trim((string) $field->name)] = $collation;

                $expr .= $collation;
            }

            $_null = mb_strtolower(trim((string) $field->null));
            if ( ! $_null || $_null == 'no')
                $expr .= ' NOT NULL';

            $_default = (string) $field->default;
            if ($_default && $_default != 'CURRENT_TIMESTAMP')
                $expr .= ' DEFAULT \'' . $_default . '\'';
            elseif ($_default == 'CURRENT_TIMESTAMP')
                $expr .= ' DEFAULT ' . $_default . '';

            $correct_table['fields'][trim((string) $field->name)] = $expr;
        }
        if ($table->indexes) {
            foreach ($table->indexes->index as $index) {
                $i_name = (string) $index->name;
                $expr = [];
                foreach ($index->fields->field as $field)
                    $expr[] = '`' . trim((string) $field) . '`';

                $expr = implode(', ', $expr);

                switch ((string) $index->type) {
                    case "PRIMARY":
                        $correct_table['indexes']['PRIMARY'] = 'PRIMARY KEY (' . $expr . ')';
                        break;
                    case "UNIQUE":
                        $correct_table['indexes'][$i_name] = 'UNIQUE KEY `' . $i_name . '` (' . $expr . ')';
                        break;
                    case "INDEX":
                        $correct_table['indexes'][$i_name] = 'KEY `' . $i_name . '` (' . $expr . ')';
                        break;
                }
            }
        }

        $sql = "SHOW FULL FIELDS FROM `" . $table['name'] . "`";
        $stmt = $this->get_connection()->prepare($sql);
        $stmt->execute();
        $rs2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        foreach ($rs2 as $row2) {
            $f_name = $row2['Field'];
            $expr_found = trim($row2['Type']);

            $_extra = $row2['Extra'];

            if ($_extra)
                $expr_found .= ' ' . $_extra;

            $_collation = $row2['Collation'];

            $current_collation = '';

            if ($_collation) {
                $_collation = explode('_', $row2['Collation']);

                $expr_found .= $current_collation = ' CHARACTER SET ' . $_collation[0] . ' COLLATE ' . implode('_', $_collation);
            }

            $_null = mb_strtolower(trim($row2['Null']));

            if ( ! $_null || $_null == 'no')
                $expr_found .= ' NOT NULL';

            $_default = $row2['Default'];

            if ($_default) {
                if (trim($row2['Type']) == 'timestamp' && $_default == 'CURRENT_TIMESTAMP')
                    $expr_found .= ' DEFAULT CURRENT_TIMESTAMP';
                else
                    $expr_found .= ' DEFAULT \'' . $_default . '\'';
            }

            if (isset($correct_table['fields'][$f_name])) {
                if (isset($correct_table['collation'][$f_name]) && $correct_table['collation'][$f_name] != $current_collation) {
                    $old_type = mb_strtolower(trim($row2['Type']));
                    $new_type = false;

                    switch ($old_type) {
                        case 'text':
                            $new_type = 'blob';
                            break;
                        case 'longtext':
                            $new_type = 'longblob';
                            break;
                        case 'mediumtext':
                            $new_type = 'mediumblob';
                            break;
                        case 'tinytext':
                            $new_type = 'tinyblob';
                            break;
                        default:
                            if (substr($old_type, 0, 4) == 'char')
                                $new_type = 'varbinary(255)';
                            if (substr($old_type, 0, 7) == 'varchar')
                                $new_type = 'varbinary(767)';
                            break;
                    }

                    if ($new_type) {
                        $alter_pre[] = "ALTER TABLE `" . $table['name'] . "` CHANGE `$f_name` `$f_name` " . $new_type . "";
                    }
                }

                if (strtolower($expr_found) !== strtolower($correct_table['fields'][$f_name])) {
                    $alter[] = "ALTER TABLE `" . $table['name'] . "` CHANGE `$f_name` `$f_name` " . $correct_table['fields'][$f_name];
                }
                unset($correct_table['fields'][$f_name]);
            } else {
                $return[] = [
                    'message' => 'Un champ pourrait etre supprime',
                    'sql'     => "ALTER TABLE " . $this->dbname . ".`" . $table['name'] . "` DROP `$f_name`;"
                ];
            }
        }

        foreach ($correct_table['fields'] as $f_name => $expr) {
            $alter[] = "ALTER TABLE `" . $table['name'] . "` ADD `$f_name` " . $correct_table['fields'][$f_name];
        }

        $tIndex = [];
        $sql = "SHOW INDEXES FROM `" . $table['name'] . "`";
        $stmt = $this->get_connection()->prepare($sql);
        $stmt->execute();
        $rs2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        foreach ($rs2 as $row2) {
            if ( ! isset($tIndex[$row2['Key_name']]))
                $tIndex[$row2['Key_name']] = ['unique'  => ((int) ($row2['Non_unique']) == 0), 'columns' => []];
            $tIndex[$row2['Key_name']]['columns'][(int) ($row2['Seq_in_index'])] = $row2['Column_name'];
        }

        foreach ($tIndex as $kIndex => $vIndex) {
            $strColumns = [];

            foreach ($vIndex['columns'] as $column)
                $strColumns[] = '`' . $column . '`';

            $strColumns = '(' . implode(', ', $strColumns) . ')';

            if ($kIndex == 'PRIMARY')
                $expr_found = 'PRIMARY KEY ' . $strColumns;
            else {
                if ($vIndex['unique'])
                    $expr_found = 'UNIQUE KEY `' . $kIndex . '` ' . $strColumns;
                else
                    $expr_found = 'KEY `' . $kIndex . '` ' . $strColumns;
            }

            $full_name_index = ($kIndex == 'PRIMARY') ? 'PRIMARY KEY' : ('INDEX `' . $kIndex . '`');

            if (isset($correct_table['indexes'][$kIndex])) {

                if (mb_strtolower($expr_found) !== mb_strtolower($correct_table['indexes'][$kIndex])) {
                    $alter[] = 'ALTER TABLE `' . $table['name'] . '` DROP ' . $full_name_index . ', ADD ' . $correct_table['indexes'][$kIndex];
                }

                unset($correct_table['indexes'][$kIndex]);
            } else {
                $return[] = [
                    'message' => 'Un index pourrait etre supprime',
                    'sql'     => 'ALTER TABLE ' . $this->dbname . '.`' . $table['name'] . '` DROP ' . $full_name_index . ';'
                ];
            }
        }

        foreach ($correct_table['indexes'] as $kIndex => $expr)
            $alter[] = 'ALTER TABLE `' . $table['name'] . '` ADD ' . $expr;

        foreach ($alter_pre as $a) {
            try {
                $stmt = $this->get_connection()->prepare($a);
                $stmt->execute();
                $stmt->closeCursor();
            } catch (\Exception $e) {
                $return[] = [
                    'message' => $this->app->trans('Erreur lors de la tentative ; errreur : %message%', ['%message%' => $e->getMessage()]),
                    'sql'     => $a
                ];
            }
        }

        foreach ($alter as $a) {
            try {
                $stmt = $this->get_connection()->prepare($a);
                $stmt->execute();
                $stmt->closeCursor();
            } catch (\Exception $e) {
                $return[] = [
                    'message' => $this->app->trans('Erreur lors de la tentative ; errreur : %message%', ['%message%' => $e->getMessage()]),
                    'sql'     => $a
                ];
            }
        }

        return $return;
    }

    protected function apply_patches($from, $to, $post_process, Application $app)
    {
        if (version::eq($from, $to)) {
            return true;
        }

        $list_patches = [];

        $iterator = new DirectoryIterator($this->app['root.path'] . '/lib/classes/patch/');

        foreach ($iterator as $fileinfo) {
            if ( ! $fileinfo->isDot()) {
                if (substr($fileinfo->getFilename(), 0, 1) == '.')
                    continue;

                $versions = array_reverse(explode('.', $fileinfo->getFilename()));
                $classname = 'patch_' . array_pop($versions);

                $patch = new $classname();

                if ( ! in_array($this->get_base_type(), $patch->concern()))
                    continue;

                if ( ! ! $post_process !== ! ! $patch->require_all_upgrades())
                    continue;

                // if patch is older than current install
                if (version::lte($patch->get_release(), $from)) {
                    continue;
                }
                // if patch is new than current target
                if (version::gt($patch->get_release(), $to)) {
                    continue;
                }

                $n = 0;
                do {
                    $key = $patch->get_release() . '.' . $n;
                    $n ++;
                } while (isset($list_patches[$key]));

                $list_patches[$key] = $patch;
            }
        }

        uasort($list_patches, function (\patchInterface $patch1, \patchInterface $patch2) {
            return version::lt($patch1->get_release(), $patch2->get_release()) ? -1 : 1;
        });

        $success = true;

        // disable mail
        $app['swiftmailer.transport'] = null;

        foreach ($list_patches as $patch) {
            // Gets doctrine migrations required for current patch
            foreach ($patch->getDoctrineMigrations() as $doctrineVersion) {
                $version = $app['doctrine-migration.configuration']->getVersion($doctrineVersion);
                // Skip if already migrated
                if ($version->isMigrated()) {
                    continue;
                }

                $migration = $version->getMigration();

                // Inject entity manager
                $migration->setEntityManager($app['orm.em']);

                // Execute migration if not marked as migrated and not already applied by an older patch
                if (!$migration->isAlreadyApplied()) {
                    $version->execute('up');
                    continue;
                }

                // Or mark it as migrated
                $version->markMigrated();
            }

            if (false === $patch->apply($this, $app)) {
                $success = false;
            }
        }

        return $success;
    }
}
