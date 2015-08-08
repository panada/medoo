<?php

namespace Panada\Medoo;

/*
 * Panada Medoo froked form Medoo database framework in https://github.com/catfan/Medoo
 * http://medoo.in
 * Version 0.9.8.3
 *
 * Copyright 2015, Angel Lai
 * Released under the MIT license
 */
class Medoo
{
    // General
    protected $databaseType;

    protected $charset;

    protected $databaseName;

    // For MySQL, MariaDB, MSSQL, Sybase, PostgreSQL, Oracle
    protected $server;

    protected $username;

    protected $password;

    // For SQLite
    protected $databaseFile;

    // For MySQL or MariaDB with unix_socket
    protected $socket;

    // Optional
    protected $port;

    protected $option = [];

    // Variable
    protected $logs = [];

    protected $debugMode = false;

    protected static $instance = [];

    // original pdo object
    public $pdo;

    public function __construct($options = null)
    {
        if ($options) {
            $this->connect($options);
        }
    }

    public static function getInstance($type = 'default')
    {
        if (!isset(self::$instance[$type])) {
            self::$instance[$type] = new static(\Panada\Resource\Config::database()[$type]);
        }

        return self::$instance[$type];
    }

    public function connect($options)
    {
        try {
            $commands = [];

            if (is_string($options) && !empty($options)) {
                if (strtolower($this->databaseType) == 'sqlite') {
                    $this->databaseFile = $options;
                } else {
                    $this->databaseName = $options;
                }
            } elseif (is_array($options)) {
                foreach ($options as $option => $value) {
                    $this->$option = $value;
                }
            }

            if (
                isset($this->port) &&
                is_int($this->port * 1)
            ) {
                $port = $this->port;
            }

            $type = strtolower($this->databaseType);
            $isPort = isset($port);

            switch ($type) {
                case 'mariadb':
                    $type = 'mysql';

                case 'mysql':
                    if ($this->socket) {
                        $dsn = $type.':unix_socket='.$this->socket.';dbname='.$this->databaseName;
                    } else {
                        $dsn = $type.':host='.$this->server.($isPort ? ';port='.$port : '').';dbname='.$this->databaseName;
                    }

                    // Make MySQL using standard quoted identifier
                    $commands[] = 'SET SQL_MODE=ANSI_QUOTES';
                    break;

                case 'pgsql':
                    $dsn = $type.':host='.$this->server.($isPort ? ';port='.$port : '').';dbname='.$this->databaseName;
                    break;

                case 'sybase':
                    $dsn = 'dblib:host='.$this->server.($isPort ? ':'.$port : '').';dbname='.$this->databaseName;
                    break;

                case 'oracle':
                    $dbname = $this->server ?
                        '//'.$this->server.($isPort ? ':'.$port : ':1521').'/'.$this->databaseName :
                        $this->databaseName;

                    $dsn = 'oci:dbname='.$dbname.($this->charset ? ';charset='.$this->charset : '');
                    break;

                case 'mssql':
                    $dsn = strstr(PHP_OS, 'WIN') ?
                        'sqlsrv:server='.$this->server.($isPort ? ','.$port : '').';database='.$this->databaseName :
                        'dblib:host='.$this->server.($isPort ? ':'.$port : '').';dbname='.$this->databaseName;

                    // Keep MSSQL QUOTED_IDENTIFIER is ON for standard quoting
                    $commands[] = 'SET QUOTED_IDENTIFIER ON';
                    break;

                case 'sqlite':
                    $dsn = $type.':'.$this->databaseFile;
                    $this->username = null;
                    $this->password = null;
                    break;
            }

            if (
                in_array($type, explode(' ', 'mariadb mysql pgsql sybase mssql')) &&
                $this->charset
            ) {
                $commands[] = "SET NAMES '".$this->charset."'";
            }

            $this->pdo = new \PDO(
                $dsn,
                $this->username,
                $this->password,
                $this->option
            );

            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            foreach ($commands as $value) {
                $this->pdo->exec($value);
            }

            return $this->pdo;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    public function query($query)
    {
        if ($this->debugMode) {
            echo $query;

            $this->debugMode = false;

            return false;
        }

        array_push($this->logs, $query);

        return $this->pdo->query($query);
    }

    public function exec($query)
    {
        if ($this->debugMode) {
            echo $query;

            $this->debugMode = false;

            return false;
        }

        array_push($this->logs, $query);

        return $this->pdo->exec($query);
    }

    public function quote($string)
    {
        return $this->pdo->quote($string);
    }

    protected function columnQuote($string)
    {
        return '"'.str_replace('.', '"."', preg_replace('/(^#|\(JSON\)\s*)/', '', $string)).'"';
    }

    protected function columnPush($columns)
    {
        if ($columns == '*') {
            return $columns;
        }

        if (is_string($columns)) {
            $columns = array($columns);
        }

        $stack = [];

        foreach ($columns as $key => $value) {
            preg_match('/([a-zA-Z0-9_\-\.]*)\s*\(([a-zA-Z0-9_\-]*)\)/i', $value, $match);

            if (isset($match[1], $match[2])) {
                array_push($stack, $this->columnQuote($match[1]).' AS '.$this->columnQuote($match[2]));
            } else {
                array_push($stack, $this->columnQuote($value));
            }
        }

        return implode($stack, ',');
    }

    protected function arrayQuote($array)
    {
        $temp = [];

        foreach ($array as $value) {
            $temp[] = is_int($value) ? $value : $this->pdo->quote($value);
        }

        return implode($temp, ',');
    }

    protected function innerConjunct($data, $conjunctor, $outer_conjunctor)
    {
        $haystack = [];

        foreach ($data as $value) {
            $haystack[] = '('.$this->dataImplode($value, $conjunctor).')';
        }

        return implode($outer_conjunctor.' ', $haystack);
    }

    protected function fnQuote($column, $string)
    {
        return (strpos($column, '#') === 0 && preg_match('/^[A-Z0-9\_]*\([^)]*\)$/', $string)) ?

            $string :

            $this->quote($string);
    }

    protected function dataImplode($data, $conjunctor, $outer_conjunctor = null)
    {
        $wheres = [];

        foreach ($data as $key => $value) {
            $type = gettype($value);

            if (
                preg_match("/^(AND|OR)(\s+#.*)?$/i", $key, $relation_match) &&
                $type == 'array'
            ) {
                $wheres[] = 0 !== count(array_diff_key($value, array_keys(array_keys($value)))) ?
                    '('.$this->dataImplode($value, ' '.$relation_match[1]).')' :
                    '('.$this->innerConjunct($value, ' '.$relation_match[1], $conjunctor).')';
            } else {
                preg_match('/(#?)([\w\.]+)(\[(\>|\>\=|\<|\<\=|\!|\<\>|\>\<|\!?~)\])?/i', $key, $match);
                $column = $this->columnQuote($match[2]);

                if (isset($match[4])) {
                    $operator = $match[4];

                    if ($operator == '!') {
                        switch ($type) {
                            case 'NULL':
                                $wheres[] = $column.' IS NOT NULL';
                                break;

                            case 'array':
                                $wheres[] = $column.' NOT IN ('.$this->arrayQuote($value).')';
                                break;

                            case 'integer':
                            case 'double':
                                $wheres[] = $column.' != '.$value;
                                break;

                            case 'boolean':
                                $wheres[] = $column.' != '.($value ? '1' : '0');
                                break;

                            case 'string':
                                $wheres[] = $column.' != '.$this->fnQuote($key, $value);
                                break;
                        }
                    }

                    if ($operator == '<>' || $operator == '><') {
                        if ($type == 'array') {
                            if ($operator == '><') {
                                $column .= ' NOT';
                            }

                            if (is_numeric($value[0]) && is_numeric($value[1])) {
                                $wheres[] = '('.$column.' BETWEEN '.$value[0].' AND '.$value[1].')';
                            } else {
                                $wheres[] = '('.$column.' BETWEEN '.$this->quote($value[0]).' AND '.$this->quote($value[1]).')';
                            }
                        }
                    }

                    if ($operator == '~' || $operator == '!~') {
                        if ($type == 'string') {
                            $value = array($value);
                        }

                        if (!empty($value)) {
                            $like_clauses = [];

                            foreach ($value as $item) {
                                if (preg_match('/^(?!%).+(?<!%)$/', $item)) {
                                    $item = '%'.$item.'%';
                                }

                                $like_clauses[] = $column . ($operator === '!~' ? ' NOT' : '') . ' LIKE ' . $this->fn_quote($key, $item);
                            }

                            $wheres[] = implode(' OR ', $like_clauses);
                        }
                    }

                    if (in_array($operator, array('>', '>=', '<', '<='))) {
                        if (is_numeric($value)) {
                            $wheres[] = $column.' '.$operator.' '.$value;
                        } elseif (strpos($key, '#') === 0) {
                            $wheres[] = $column.' '.$operator.' '.$this->fnQuote($key, $value);
                        } else {
                            $wheres[] = $column.' '.$operator.' '.$this->quote($value);
                        }
                    }
                } else {
                    switch ($type) {
                        case 'NULL':
                            $wheres[] = $column.' IS NULL';
                            break;

                        case 'array':
                            $wheres[] = $column.' IN ('.$this->arrayQuote($value).')';
                            break;

                        case 'integer':
                        case 'double':
                            $wheres[] = $column.' = '.$value;
                            break;

                        case 'boolean':
                            $wheres[] = $column.' = '.($value ? '1' : '0');
                            break;

                        case 'string':
                            $wheres[] = $column.' = '.$this->fnQuote($key, $value);
                            break;
                    }
                }
            }
        }

        return implode($conjunctor.' ', $wheres);
    }

    protected function whereClause($where)
    {
        $whereClause = '';

        if (is_array($where)) {
            $where_keys = array_keys($where);
            $where_AND = preg_grep("/^AND\s*#?$/i", $where_keys);
            $where_OR = preg_grep("/^OR\s*#?$/i", $where_keys);

            $single_condition = array_diff_key($where, array_flip(
                explode(' ', 'AND OR GROUP ORDER HAVING LIMIT LIKE MATCH')
            ));

            if ($single_condition != []) {
                $whereClause = ' WHERE '.$this->dataImplode($single_condition, '');
            }

            if (!empty($where_AND)) {
                $value = array_values($where_AND);
                $whereClause = ' WHERE '.$this->dataImplode($where[ $value[0] ], ' AND');
            }

            if (!empty($where_OR)) {
                $value = array_values($where_OR);
                $whereClause = ' WHERE '.$this->dataImplode($where[ $value[0] ], ' OR');
            }

            if (isset($where['MATCH'])) {
                $MATCH = $where['MATCH'];

                if (is_array($MATCH) && isset($MATCH['columns'], $MATCH['keyword'])) {
                    $whereClause .= ($whereClause != '' ? ' AND ' : ' WHERE ').' MATCH ("'.str_replace('.', '"."', implode($MATCH['columns'], '", "')).'") AGAINST ('.$this->quote($MATCH['keyword']).')';
                }
            }

            if (isset($where['GROUP'])) {
                $whereClause .= ' GROUP BY '.$this->columnQuote($where['GROUP']);

                if (isset($where['HAVING'])) {
                    $whereClause .= ' HAVING '.$this->dataImplode($where['HAVING'], ' AND');
                }
            }

            if (isset($where['ORDER'])) {
                $rsort = '/(^[a-zA-Z0-9_\-\.]*)(\s*(DESC|ASC))?/';
                $ORDER = $where['ORDER'];

                if (is_array($ORDER)) {
                    if (
                        isset($ORDER[1]) &&
                        is_array($ORDER[1])
                    ) {
                        $whereClause .= ' ORDER BY FIELD('.$this->columnQuote($ORDER[0]).', '.$this->arrayQuote($ORDER[1]).')';
                    } else {
                        $stack = [];

                        foreach ($ORDER as $column) {
                            preg_match($rsort, $column, $order_match);

                            array_push($stack, '"'.str_replace('.', '"."', $order_match[1]).'"'.(isset($order_match[3]) ? ' '.$order_match[3] : ''));
                        }

                        $whereClause .= ' ORDER BY '.implode($stack, ',');
                    }
                } else {
                    preg_match($rsort, $ORDER, $order_match);

                    $whereClause .= ' ORDER BY "'.str_replace('.', '"."', $order_match[1]).'"'.(isset($order_match[3]) ? ' '.$order_match[3] : '');
                }
            }

            if (isset($where['LIMIT'])) {
                $LIMIT = $where['LIMIT'];

                if (is_numeric($LIMIT)) {
                    $whereClause .= ' LIMIT '.$LIMIT;
                }

                if (
                    is_array($LIMIT) &&
                    is_numeric($LIMIT[0]) &&
                    is_numeric($LIMIT[1])
                ) {
                    if ($this->databaseType === 'pgsql') {
                        $whereClause .= ' OFFSET '.$LIMIT[0].' LIMIT '.$LIMIT[1];
                    } else {
                        $whereClause .= ' LIMIT '.$LIMIT[0].','.$LIMIT[1];
                    }
                }
            }
        } else {
            if ($where != null) {
                $whereClause .= ' '.$where;
            }
        }

        return $whereClause;
    }

    protected function selectContext($table, $join, &$columns = null, $where = null, $column_fn = null)
    {
        $table = '"'.$table.'"';
        $join_key = is_array($join) ? array_keys($join) : null;

        if (
            isset($join_key[0]) &&
            strpos($join_key[0], '[') === 0
        ) {
            $table_join = [];

            $join_array = array(
                '>' => 'LEFT',
                '<' => 'RIGHT',
                '<>' => 'FULL',
                '><' => 'INNER',
            );

            foreach ($join as $sub_table => $relation) {
                preg_match('/(\[(\<|\>|\>\<|\<\>)\])?([a-zA-Z0-9_\-]*)\s?(\(([a-zA-Z0-9_\-]*)\))?/', $sub_table, $match);

                if ($match[2] != '' && $match[3] != '') {
                    if (is_string($relation)) {
                        $relation = 'USING ("'.$relation.'")';
                    }

                    if (is_array($relation)) {
                        // For ['column1', 'column2']
                        if (isset($relation[0])) {
                            $relation = 'USING ("'.implode($relation, '", "').'")';
                        } else {
                            $joins = [];

                            foreach ($relation as $key => $value) {
                                $joins[] = (
                                    strpos($key, '.') > 0 ?
                                        // For ['tableB.column' => 'column']
                                        '"'.str_replace('.', '"."', $key).'"' :

                                        // For ['column1' => 'column2']
                                        $table.'."'.$key.'"'
                                ).
                                ' = '.
                                '"'.(isset($match[5]) ? $match[5] : $match[3]).'"."'.$value.'"';
                            }

                            $relation = 'ON '.implode($joins, ' AND ');
                        }
                    }

                    $table_join[] = $join_array[ $match[2] ].' JOIN "'.$match[3].'" '.(isset($match[5]) ?  'AS "'.$match[5].'" ' : '').$relation;
                }
            }

            $table .= ' '.implode($table_join, ' ');
        } else {
            if (is_null($columns)) {
                if (is_null($where)) {
                    if (
                        is_array($join) &&
                        isset($column_fn)
                    ) {
                        $where = $join;
                        $columns = null;
                    } else {
                        $where = null;
                        $columns = $join;
                    }
                } else {
                    $where = $join;
                    $columns = null;
                }
            } else {
                $where = $columns;
                $columns = $join;
            }
        }

        if (isset($column_fn)) {
            if ($column_fn == 1) {
                $column = '1';

                if (is_null($where)) {
                    $where = $columns;
                }
            } else {
                if (empty($columns)) {
                    $columns = '*';
                    $where = $join;
                }

                $column = $column_fn.'('.$this->columnPush($columns).')';
            }
        } else {
            $column = $this->columnPush($columns);
        }

        return 'SELECT '.$column.' FROM '.$table.$this->whereClause($where);
    }

    public function select($table, $join, $columns = null, $where = null)
    {
        return $this->query($this->selectContext($table, $join, $columns, $where));
    }

    public function insert($table, $datas)
    {
        $lastId = [];

        // Check indexed or associative array
        if (!isset($datas[0])) {
            $datas = array($datas);
        }

        foreach ($datas as $data) {
            $values = [];
            $columns = [];

            foreach ($data as $key => $value) {
                array_push($columns, $this->columnQuote($key));

                switch (gettype($value)) {
                    case 'NULL':
                        $values[] = 'NULL';
                        break;

                    case 'array':
                        preg_match("/\(JSON\)\s*([\w]+)/i", $key, $column_match);

                        $values[] = isset($column_match[0]) ?
                            $this->quote(json_encode($value)) :
                            $this->quote(serialize($value));
                        break;

                    case 'boolean':
                        $values[] = ($value ? '1' : '0');
                        break;

                    case 'integer':
                    case 'double':
                    case 'string':
                        $values[] = $this->fnQuote($key, $value);
                        break;
                }
            }

            $this->exec('INSERT INTO "'.$table.'" ('.implode(', ', $columns).') VALUES ('.implode($values, ', ').')');

            $lastId[] = $this->pdo->lastInsertId();
        }

        return count($lastId) > 1 ? $lastId : $lastId[ 0 ];
    }

    public function update($table, $data, $where = null)
    {
        $fields = [];

        foreach ($data as $key => $value) {
            preg_match('/([\w]+)(\[(\+|\-|\*|\/)\])?/i', $key, $match);

            if (isset($match[3])) {
                if (is_numeric($value)) {
                    $fields[] = $this->columnQuote($match[1]).' = '.$this->columnQuote($match[1]).' '.$match[3].' '.$value;
                }
            } else {
                $column = $this->columnQuote($key);

                switch (gettype($value)) {
                    case 'NULL':
                        $fields[] = $column.' = NULL';
                        break;

                    case 'array':
                        preg_match("/\(JSON\)\s*([\w]+)/i", $key, $column_match);

                        $fields[] = $column.' = '.$this->quote(
                                isset($column_match[0]) ? json_encode($value) : serialize($value)
                            );
                        break;

                    case 'boolean':
                        $fields[] = $column.' = '.($value ? '1' : '0');
                        break;

                    case 'integer':
                    case 'double':
                    case 'string':
                        $fields[] = $column.' = '.$this->fnQuote($key, $value);
                        break;
                }
            }
        }

        return $this->exec('UPDATE "'.$table.'" SET '.implode(', ', $fields).$this->whereClause($where));
    }

    public function delete($table, $where)
    {
        return $this->exec('DELETE FROM "'.$table.'"'.$this->whereClause($where));
    }

    public function replace($table, $columns, $search = null, $replace = null, $where = null)
    {
        if (is_array($columns)) {
            $replace_query = [];

            foreach ($columns as $column => $replacements) {
                foreach ($replacements as $replace_search => $replace_replacement) {
                    $replace_query[] = $column.' = REPLACE('.$this->columnQuote($column).', '.$this->quote($replace_search).', '.$this->quote($replace_replacement).')';
                }
            }

            $replace_query = implode(', ', $replace_query);
            $where = $search;
        } else {
            if (is_array($search)) {
                $replace_query = [];

                foreach ($search as $replace_search => $replace_replacement) {
                    $replace_query[] = $columns.' = REPLACE('.$this->columnQuote($columns).', '.$this->quote($replace_search).', '.$this->quote($replace_replacement).')';
                }

                $replace_query = implode(', ', $replace_query);
                $where = $replace;
            } else {
                $replace_query = $columns.' = REPLACE('.$this->columnQuote($columns).', '.$this->quote($search).', '.$this->quote($replace).')';
            }
        }

        return $this->exec('UPDATE "'.$table.'" SET '.$replace_query.$this->whereClause($where));
    }

    public function get($table, $join = null, $column = null, $where = null)
    {
        $query = $this->query($this->selectContext($table, $join, $column, $where).' LIMIT 1');

        if ($query) {
            $data = $query->fetchAll(\PDO::FETCH_ASSOC);

            if (isset($data[0])) {
                $column = $where == null ? $join : $column;

                if (is_string($column) && $column != '*') {
                    return $data[ 0 ][ $column ];
                }

                return $data[ 0 ];
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function has($table, $join, $where = null)
    {
        $column = null;

        $query = $this->query('SELECT EXISTS('.$this->selectContext($table, $join, $column, $where, 1).')');

        return $query ? $query->fetchColumn() === '1' : false;
    }

    public function count($table, $join = null, $column = null, $where = null)
    {
        $query = $this->query($this->selectContext($table, $join, $column, $where, 'COUNT'));

        return $query ? 0 + $query->fetchColumn() : false;
    }

    public function max($table, $join, $column = null, $where = null)
    {
        $query = $this->query($this->selectContext($table, $join, $column, $where, 'MAX'));

        if ($query) {
            $max = $query->fetchColumn();

            return is_numeric($max) ? $max + 0 : $max;
        } else {
            return false;
        }
    }

    public function min($table, $join, $column = null, $where = null)
    {
        $query = $this->query($this->selectContext($table, $join, $column, $where, 'MIN'));

        if ($query) {
            $min = $query->fetchColumn();

            return is_numeric($min) ? $min + 0 : $min;
        } else {
            return false;
        }
    }

    public function avg($table, $join, $column = null, $where = null)
    {
        $query = $this->query($this->selectContext($table, $join, $column, $where, 'AVG'));

        return $query ? 0 + $query->fetchColumn() : false;
    }

    public function sum($table, $join, $column = null, $where = null)
    {
        $query = $this->query($this->selectContext($table, $join, $column, $where, 'SUM'));

        return $query ? 0 + $query->fetchColumn() : false;
    }
    
    public function action($actions)
	{
		if (is_callable($actions)) {
            $this->pdo->beginTransaction();
			$result = $actions($this);
			if ($result === false) {
				$this->pdo->rollBack();
			}
			else
			{
				$this->pdo->commit();
			}
		}
		else
		{
			return false;
		}
	}

    public function debug()
    {
        $this->debugMode = true;

        return $this;
    }

    public function error()
    {
        return $this->pdo->errorInfo();
    }

    public function lastQuery()
    {
        return end($this->logs);
    }

    public function log()
    {
        return $this->logs;
    }

    public function info()
    {
        $output = array(
            'server' => 'SERVER_INFO',
            'driver' => 'DRIVER_NAME',
            'client' => 'CLIENT_VERSION',
            'version' => 'SERVER_VERSION',
            'connection' => 'CONNECTION_STATUS',
        );

        foreach ($output as $key => $value) {
            $output[ $key ] = $this->pdo->getAttribute(constant('\\PDO::ATTR_'.$value));
        }

        return $output;
    }
}
