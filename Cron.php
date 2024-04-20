<?php

namespace localzet;

class Cron
{
    /**
     * @var string Строка правила cron
     */
    protected $_rule;

    /**
     * @var callable Функция обратного вызова для выполнения
     */
    protected $_callback;

    /**
     * @var string Имя задания cron
     */
    protected $_name;

    /**
     * @var int Уникальный идентификатор задания cron
     */
    protected $_id;

    /**
     * @var array Список всех экземпляров заданий cron
     */
    protected static $_instances = [];

    /**
     * Конструктор Crontab.
     *
     * @param string $rule Правило cron
     * @param callable $callback Функция обратного вызова для выполнения
     * @param string $name Имя задания cron
     */
    public function __construct($rule, $callback, $name = '')
    {
        $this->_rule = $rule;
        $this->_callback = $callback;
        $this->_name = $name;
        $this->_id = static::createId();
        static::$_instances[$this->_id] = $this;
        static::tryInit();
    }

    /**
     * Получить правило cron.
     *
     * @return string
     */
    public function getRule()
    {
        return $this->_rule;
    }

    /**
     * Получить функцию обратного вызова.
     *
     * @return callable
     */
    public function getCallback()
    {
        return $this->_callback;
    }

    /**
     * Получить имя задания cron.
     *
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * Получить уникальный идентификатор задания cron.
     *
     * @return int
     */
    public function getId()
    {
        return $this->_id;
    }

    /**
     * Удалить текущее задание cron.
     *
     * @return bool
     */
    public function destroy()
    {
        return static::remove($this->_id);
    }

    /**
     * Получить все задания cron.
     *
     * @return array
     */
    public static function getAll()
    {
        return static::$_instances;
    }

    /**
     * Удалить задание cron по идентификатору.
     *
     * @param $id
     * @return bool
     */
    public static function remove($id)
    {
        if ($id instanceof Cron) {
            $id = $id->getId();
        }
        if (!isset(static::$_instances[$id])) {
            return false;
        }
        unset(static::$_instances[$id]);
        return true;
    }

    /**
     * Создать уникальный идентификатор для задания cron.
     *
     * @return int
     */
    protected static function createId()
    {
        static $id = 0;
        return ++$id;
    }

    /**
     * Попытаться инициализировать задания cron.
     */
    protected static function tryInit()
    {
        static $inited = false;
        if ($inited) {
            return;
        }
        $inited = true;
        $parser = new Parser();
        $callback = function () use ($parser, &$callback) {
            foreach (static::$_instances as $crontab) {
                $rule = $crontab->getRule();
                $cb = $crontab->getCallback();
                if (!$cb || !$rule) {
                    continue;
                }
                $times = $parser->parse($rule);
                $now = time();
                foreach ($times as $time) {
                    $t = $time - $now;
                    if ($t <= 0) {
                        $t = 0.000001;
                    }
                    Timer::add($t, $cb, null, false);
                }
            }
            Timer::add(60 - time() % 60, $callback, null, false);
        };

        $next_time = time() % 60;
        if ($next_time == 0) {
            $next_time = 0.00001;
        } else {
            $next_time = 60 - $next_time;
        }
        Timer::add($next_time, $callback, null, false);
    }
}