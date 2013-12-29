<?php

namespace Frontend\Core\Engine;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

/**
 * This class will store the language-dependant content for the frontend.
 *
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 */
class Language
{
    /**
     * Locale arrays
     *
     * @var    array
     */
    private static $act = array();
    private static $err = array();
    private static $lbl = array();
    private static $msg = array();

    /**
     * Locale fallback arrays
     *
     * @var    array
     */
    private static $fallbackAct = array();
    private static $fallbackErr = array();
    private static $fallbackLbl = array();
    private static $fallbackMsg = array();

    /**
     * The possible languages
     *
     * @var    array
     */
    private static $languages = array('active' => array(), 'possible_redirect' => array());

    /**
     * Build the language files
     *
     * @param string $language    The language to build the locale-file for.
     * @param string $application The application to build the locale-file for.
     */
    public static function buildCache($language, $application)
    {
        $db = Model::getContainer()->get('database');

        // get types
        $types = $db->getEnumValues('locale', 'type');

        // get locale for backend
        $locale = (array) $db->getRecords(
            'SELECT type, module, name, value
             FROM locale
             WHERE language = ? AND application = ?
             ORDER BY type ASC, name ASC, module ASC',
            array((string) $language, (string) $application)
        );

        // init var
        $json = array();

        // start generating PHP
        $value = '<?php' . "\n";
        $value .= '/**' . "\n";
        $value .= ' *' . "\n";
        $value .= ' * This file is generated by Fork CMS, it contains' . "\n";
        $value .= ' * more information about the locale. Do NOT edit.' . "\n";
        $value .= ' * ' . "\n";
        $value .= ' * @author Fork CMS' . "\n";
        $value .= ' * @generated	' . date('Y-m-d H:i:s') . "\n";
        $value .= ' */' . "\n";
        $value .= "\n";

        // loop types
        foreach ($types as $type) {
            // default module
            $modules = array('core');

            // continue output
            $value .= "\n";
            $value .= '// init var' . "\n";
            $value .= '$' . $type . ' = array();' . "\n";

            // loop locale
            foreach ($locale as $i => $item) {
                // types match
                if ($item['type'] == $type) {
                    // new module
                    if (!in_array($item['module'], $modules)) {
                        $value .= '$' . $type . '[\'' . $item['module'] . '\'] = array();' . "\n";
                        $modules[] = $item['module'];
                    }

                    // parse
                    if ($application == 'backend') {
                        $value .= '$' . $type . '[\'' . $item['module'] . '\'][\'' . $item['name'] . '\'] = \'' .
                                  str_replace(
                                      '\"',
                                      '"',
                                      addslashes($item['value'])
                                  ) . '\';' . "\n";
                        $json[$type][$item['module']][$item['name']] = $item['value'];
                    } else {
                        $value .= '$' . $type . '[\'' . $item['name'] . '\'] = \'' .
                                  str_replace(
                                      '\"',
                                      '"',
                                      addslashes($item['value'])
                                  ) . '\';' . "\n";
                        $json[$type][$item['name']] = $item['value'];
                    }

                    // unset
                    unset($locale[$i]);
                }
            }
        }

        $value .= "\n";
        $value .= '?>';

        $fs = new Filesystem();

        // store
        $fs->dumpFile(
            constant(mb_strtoupper($application) . '_CACHE_PATH') . '/Locale/' . $language . '.php',
            $value
        );

        // get months
        $monthsLong = \SpoonLocale::getMonths($language, false);
        $monthsShort = \SpoonLocale::getMonths($language, true);

        // get days
        $daysLong = \SpoonLocale::getWeekDays($language, false, 'sunday');
        $daysShort = \SpoonLocale::getWeekDays($language, true, 'sunday');

        // build labels
        foreach ($monthsLong as $key => $value) {
            $json['loc']['MonthLong' . \SpoonFilter::ucfirst($key)] = $value;
        }
        foreach ($monthsShort as $key => $value) {
            $json['loc']['MonthShort' . \SpoonFilter::ucfirst($key)] = $value;
        }
        foreach ($daysLong as $key => $value) {
            $json['loc']['DayLong' . \SpoonFilter::ucfirst($key)] = $value;
        }
        foreach ($daysShort as $key => $value) {
            $json['loc']['DayShort' . \SpoonFilter::ucfirst($key)] = $value;
        }

        // store
        $fs->dumpFile(
            constant(mb_strtoupper($application) . '_CACHE_PATH') . '/Locale/' . $language . '.json',
            json_encode($json)
        );
    }

    /**
     * Get an action from the language-file
     *
     * @param string $key      The key to get.
     * @param bool   $fallback Should we provide a fallback in English?
     * @return string
     */
    public static function getAction($key, $fallback = true)
    {
        // redefine
        $key = \SpoonFilter::toCamelCase((string) $key);

        // if the action exists return it,
        if (isset(self::$act[$key])) {
            return self::$act[$key];
        }

        // If we should fallback and the fallback label exists, return it
        if (isset(self::$fallbackAct[$key]) && $fallback === true && SPOON_DEBUG === false) {
            return self::$fallbackAct[$key];
        }

        // otherwise return the key in label-format
        return '{$act' . $key . '}';
    }

    /**
     * Get all the actions
     *
     * @return array
     */
    public static function getActions()
    {
        return (SPOON_DEBUG === true) ? self::$act : array_merge(self::$fallbackAct, self::$act);
    }

    /**
     * Get the active languages
     *
     * @return array
     */
    public static function getActiveLanguages()
    {
        // validate the cache
        if (empty(self::$languages['active'])) {
            // grab from settings
            $activeLanguages = (array) Model::getModuleSetting('core', 'active_languages');

            // store in cache
            self::$languages['active'] = $activeLanguages;
        }

        // return from cache
        return self::$languages['active'];
    }

    /**
     * Get the preferred language by using the browser-language
     *
     * @param bool $forRedirect Only look in the languages to redirect?
     * @return string
     */
    public static function getBrowserLanguage($forRedirect = true)
    {
        // browser language set
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && strlen($_SERVER['HTTP_ACCEPT_LANGUAGE']) >= 2) {
            // get languages
            $redirectLanguages = self::getRedirectLanguages();

            // preferred languages
            $acceptedLanguages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            $browserLanguages = array();

            foreach ($acceptedLanguages as $language) {
                $qPos = strpos($language, 'q=');
                $weight = 1;

                if ($qPos !== false) {
                    $endPos = strpos($language, ';', $qPos);
                    $weight = ($endPos === false) ? (float) substr($language, $qPos + 2) : (float) substr(
                        $language,
                        $qPos + 2,
                        $endPos
                    );
                }

                $browserLanguages[$language] = $weight;
            }

            // sort by weight
            arsort($browserLanguages);

            // loop until result
            foreach (array_keys($browserLanguages) as $language) {
                // redefine language
                $language = substr($language, 0, 2); // first two characters

                // find possible language
                if ($forRedirect) {
                    // check in the redirect-languages
                    if (in_array($language, $redirectLanguages)) {
                        return $language;
                    }
                }
            }
        }

        // fallback
        return SITE_DEFAULT_LANGUAGE;
    }

    /**
     * Get an error from the language-file
     *
     * @param string $key      The key to get.
     * @param bool   $fallback Should we provide a fallback in English?
     * @return string
     */
    public static function getError($key, $fallback = true)
    {
        // redefine
        $key = \SpoonFilter::toCamelCase((string) $key);

        // if the error exists return it,
        if (isset(self::$err[$key])) {
            return self::$err[$key];
        }

        // If we should fallback and the fallback label exists, return it
        if (isset(self::$fallbackErr[$key]) && $fallback === true && SPOON_DEBUG === false) {
            return self::$fallbackErr[$key];
        }

        // otherwise return the key in label-format
        return '{$err' . $key . '}';
    }

    /**
     * Get all the errors
     *
     * @return array
     */
    public static function getErrors()
    {
        return (SPOON_DEBUG === true) ? self::$err : array_merge(self::$fallbackErr, self::$err);
    }

    /**
     * Get a label from the language-file
     *
     * @param string $key      The key to get.
     * @param bool   $fallback Should we provide a fallback in English?
     * @return string
     */
    public static function getLabel($key, $fallback = true)
    {
        // redefine
        $key = \SpoonFilter::toCamelCase((string) $key);

        // if the error exists return it,
        if (isset(self::$lbl[$key])) {
            return self::$lbl[$key];
        }

        // If we should fallback and the fallback label exists, return it
        if (isset(self::$fallbackLbl[$key]) && $fallback === true && SPOON_DEBUG === false) {
            return self::$fallbackLbl[$key];
        }

        // otherwise return the key in label-format
        return '{$lbl' . $key . '}';
    }

    /**
     * Get all the labels
     *
     * @return array
     */
    public static function getLabels()
    {
        return (SPOON_DEBUG === true) ? self::$lbl : array_merge(self::$fallbackLbl, self::$lbl);
    }

    /**
     * Get a message from the language-file
     *
     * @param string $key      The key to get.
     * @param bool   $fallback Should we provide a fallback in English?
     * @return string
     */
    public static function getMessage($key, $fallback = true)
    {
        // redefine
        $key = \SpoonFilter::toCamelCase((string) $key);

        // if the error exists return it,
        if (isset(self::$msg[$key])) {
            return self::$msg[$key];
        }

        // If we should fallback and the fallback label exists, return it
        if (isset(self::$fallbackMsg[$key]) && $fallback === true && SPOON_DEBUG === false) {
            return self::$fallbackMsg[$key];
        }

        // otherwise return the key in label-format
        return '{$msg' . $key . '}';
    }

    /**
     * Get all the messages
     *
     * @return array
     */
    public static function getMessages()
    {
        return (SPOON_DEBUG === true) ? self::$msg : array_merge(self::$fallbackMsg, self::$msg);
    }

    /**
     * Get the redirect languages
     *
     * @return array
     */
    public static function getRedirectLanguages()
    {
        // validate the cache
        if (empty(self::$languages['possible_redirect'])) {
            // grab from settings
            $redirectLanguages = (array) Model::getModuleSetting('core', 'redirect_languages');

            // store in cache
            self::$languages['possible_redirect'] = $redirectLanguages;
        }

        // return
        return self::$languages['possible_redirect'];
    }

    /**
     * Set locale
     *
     * @param string $language The language to load, if not provided we will load the language based on the URL.
     * @param bool   $force    Force the language, so don't check if the language is active.
     */
    public static function setLocale($language = null, $force = false)
    {
        // redefine
        $language = ($language !== null) ? (string) $language : FRONTEND_LANGUAGE;

        // validate language
        if (!$force && !in_array($language, self::getActiveLanguages())) {
            throw new Exception('Invalid language (' . $language . ').');
        }

        // validate file, generate it if needed
        if (!is_file(FRONTEND_CACHE_PATH . '/Locale/en.php')) {
            self::buildCache('en', 'frontend');
        }
        if (!is_file(FRONTEND_CACHE_PATH . '/Locale/' . $language . '.php')) {
            self::buildCache($language, 'frontend');
        }

        // init vars
        $act = array();
        $err = array();
        $lbl = array();
        $msg = array();

        // set English translations, they'll be the fallback
        require FRONTEND_CACHE_PATH . '/Locale/en.php';
        self::$fallbackAct = (array) $act;
        self::$fallbackErr = (array) $err;
        self::$fallbackLbl = (array) $lbl;
        self::$fallbackMsg = (array) $msg;

        // We will overwrite with the requested language's translations upon request
        require FRONTEND_CACHE_PATH . '/Locale/' . $language . '.php';
        self::$act = (array) $act;
        self::$err = (array) $err;
        self::$lbl = (array) $lbl;
        self::$msg = (array) $msg;
    }

    /**
     * Get an action from the language-file
     *
     * @param string $key      The key to get.
     * @param bool   $fallback Should we provide a fallback in English?
     * @return string
     */
    public static function act($key, $fallback = true)
    {
        return self::getAction($key, $fallback);
    }

    /**
     * Get an error from the language-file
     *
     * @param string $key      The key to get.
     * @param bool   $fallback Should we provide a fallback in English?
     * @return string
     */
    public static function err($key, $fallback = true)
    {
        return self::getError($key, $fallback);
    }

    /**
     * Get a label from the language-file
     *
     * @param string $key      The key to get.
     * @param bool   $fallback Should we provide a fallback in English?
     * @return string
     */
    public static function lbl($key, $fallback = true)
    {
        return self::getLabel($key, $fallback);
    }

    /**
     * Get a message from the language-file
     *
     * @param string $key      The key to get.
     * @param bool   $fallback Should we provide a fallback in English?
     * @return string
     */
    public static function msg($key, $fallback = true)
    {
        return self::getMessage($key, $fallback);
    }
}
