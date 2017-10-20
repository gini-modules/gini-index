<?php

namespace Gini\Logger;

class IndexLog extends Handler
{
    private static $_LEVEL2PRIORITY = [
        Level::EMERGENCY => \LOG_EMERG,
        Level::ALERT => \LOG_ALERT,
        Level::CRITICAL => \LOG_CRIT,
        Level::ERROR => \LOG_ERR,
        Level::WARNING => \LOG_WARNING,
        Level::NOTICE => \LOG_NOTICE,
        Level::INFO => \LOG_INFO,
        Level::DEBUG => \LOG_DEBUG,
    ];

    public function log($level, $message, array $context = array())
    {
        if (!$this->isLoggable($level)) {
            return;
        }

        $replacements = [];
        $_fillReplacements = function (&$replacements, $context, $prefix = '') use (&$_fillReplacements) {
            foreach ($context as $key => $val) {
                if (is_array($val)) {
                    $_fillReplacements($replacements, $val, $prefix.$key.'.');
                } else {
                    $replacements['{'.$prefix.$key.'}'] = $val;
                }
            }
        };
        $_fillReplacements($replacements, $context);

        $context['timestamp'] = date('Y-m-d H:i:s');
        $context['message'] = strtr($message, $replacements);

        $fh = fopen(self::logFile(), 'a');
        fwrite($fh, J($context)."\n");
        fclose($fh);
    }

    public static function logFile()
    {
        return APP_PATH.'/'.DATA_DIR.'/audit.log';
    }
}
