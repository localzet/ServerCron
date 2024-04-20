<?php

namespace localzet;


use InvalidArgumentException;

class Parser
{
    /**
     * Находит следующее время выполнения, разбирая синтаксис crontab.
     *
     * @param string $crontab_string :
     *   0    1    2    3    4    5
     *   *    *    *    *    *    *
     *   -    -    -    -    -    -
     *   |    |    |    |    |    |
     *   |    |    |    |    |    +----- day of week (0 - 6) (Воскресенье=0)
     *   |    |    |    |    +----- month (1 - 12)
     *   |    |    |    +------- day of month (1 - 31)
     *   |    |    +--------- hour (0 - 23)
     *   |    +----------- min (0 - 59)
     *   +------------- sec (0-59)
     *
     * @param int|null $start_time
     * @return int[]
     * @throws InvalidArgumentException
     */
    public function parse($crontab_string, $start_time = null)
    {
        if (!$this->isValid($crontab_string)) {
            throw new InvalidArgumentException('Invalid cron string: ' . $crontab_string);
        }
        $start_time = $start_time ?: time();
        $date = $this->parseDate($crontab_string);
        if (in_array((int)date('i', $start_time), $date['minutes'])
            && in_array((int)date('G', $start_time), $date['hours'])
            && in_array((int)date('j', $start_time), $date['day'])
            && in_array((int)date('w', $start_time), $date['week'])
            && in_array((int)date('n', $start_time), $date['month'])
        ) {
            return array_map(
                function ($second) use ($start_time) {
                    return $start_time + $second;
                },
                $date['second']
            );
        }
        return [];
    }

    /**
     * @param string $crontab_string
     * @return bool
     */
    public function isValid(string $crontab_string): bool
    {
        if (!preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($crontab_string))) {
            if (!preg_match('/^((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)\s+((\*(\/[0-9]+)?)|[0-9\-\,\/]+)$/i', trim($crontab_string))) {
                return false;
            }
        }
        return true;
    }

    /**
     *  Разбирает каждый сегмент строки crontab.
     * @param string $string
     * @param int $min
     * @param int $max
     * @param int|null $start
     * @return array
     */
    protected function parseSegment(string $string, int $min, int $max, int $start = null)
    {
        $start = $start < $min ? $min : $start;
        $result = [];
        if ($string === '*') {
            $result = range($start, $max);
        } elseif (strpos($string, ',') !== false) {
            $exploded = explode(',', $string);
            foreach ($exploded as $value) {
                if (strpos($value, '/') !== false || strpos($string, '-') !== false) {
                    $result = array_merge($result, $this->parseSegment($value, $min, $max, $start));
                    continue;
                }
                if (trim($value) === '' || !$this->between((int)$value, (int)($min > $start ? $min : $start), (int)$max)) {
                    continue;
                }
                $result[] = (int)$value;
            }
        } elseif (strpos($string, '/') !== false) {
            $exploded = explode('/', $string);
            if (strpos($exploded[0], '-') !== false) {
                [$nMin, $nMax] = explode('-', $exploded[0]);
                $nMin > $min && $min = (int)$nMin;
                $nMax < $max && $max = (int)$nMax;
            }
            $start < $min && $start = $min;
            for ($i = $start; $i <= $max;) {
                $result[] = $i;
                $i += $exploded[1];
            }
        } elseif (strpos($string, '-') !== false) {
            $result = array_merge($result, $this->parseSegment($string . '/1', $min, $max, $start));
        } elseif ($this->between((int)$string, $min > $start ? $min : $start, $max)) {
            $result[] = (int)$string;
        }
        return $result;
    }

    /**
     * Определяет, находится ли $value между $min и $max.
     * @param int $value
     * @param int $min
     * @param int $max
     * @return bool
     */
    private function between(int $value, int $min, int $max): bool
    {
        return $value >= $min && $value <= $max;
    }


    /**
     * Разбирает дату из строки crontab.
     * @param string $crontab_string
     * @return array
     */
    private function parseDate(string $crontab_string): array
    {
        $cron = preg_split('/[\\s]+/i', trim($crontab_string));
        if (count($cron) == 6) {
            $date = [
                'second' => $this->parseSegment($cron[0], 0, 59),
                'minutes' => $this->parseSegment($cron[1], 0, 59),
                'hours' => $this->parseSegment($cron[2], 0, 23),
                'day' => $this->parseSegment($cron[3], 1, 31),
                'month' => $this->parseSegment($cron[4], 1, 12),
                'week' => $this->parseSegment($cron[5], 0, 6),
            ];
        } else {
            $date = [
                'second' => [1 => 0],
                'minutes' => $this->parseSegment($cron[0], 0, 59),
                'hours' => $this->parseSegment($cron[1], 0, 23),
                'day' => $this->parseSegment($cron[2], 1, 31),
                'month' => $this->parseSegment($cron[3], 1, 12),
                'week' => $this->parseSegment($cron[4], 0, 6),
            ];
        }
        return $date;
    }
}