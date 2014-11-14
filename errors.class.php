<?php

namespace Errors;

/**
 *            Classe de gestion des erreurs et exceptions
 *
 * @author    Quentin Ligier
 * @version   1.0
 * @link      https://github.com/PrimalPHP/ErrorHandler/blob/master/lib/Primal/ErrorHandler.php   Projet similaire, ce projet en est quasi un fork
 * @link      https://github.com/Kryptos/ErrorHandler/blob/master/Kryptos/Handler/Error.php       Projet similaire de gestion d'erreur
 * @link      https://github.com/gehaxelt/PHP-Logger-Class/blob/master/logger.class.php           Projet similaire de log d'erreur
 */
class ErrorHandler {

    // Enregistrer les erreurs dans un fichier ?
    private $logErrors = false;

    // Niveau d'affichage des erreurs (E_ALL, E_WARNING, ...)
    private $levelReporting = 0;

    // Afficher un rapport d'erreur détaillé ?
    private $showDetError = false;

    // Constantes des erreurs
    private $errorTypes = array(
        E_ERROR             => 'Fatal Error',
        E_WARNING           => 'Warning',
        E_PARSE             => 'Parse error',
        E_NOTICE            => 'Notice',
        E_CORE_ERROR        => 'Core Error',
        E_CORE_WARNING      => 'Core Warning',
        E_COMPILE_ERROR     => 'Compile Error',
        E_COMPILE_WARNING   => 'Compile Warning',
        E_USER_ERROR        => 'User Error',
        E_USER_WARNING      => 'User Warning',
        E_USER_NOTICE       => 'User Notice',
        E_STRICT            => 'Strict Notice',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED        => 'Deprecated Notice',
        E_USER_DEPRECATED   => 'User Deprecated Notice',
        E_ALL               => 'All Errors',
    );




    /* ************************* PARTIE PUBLIQUE *************************** */


    /**
     * Initialise le gestionnaire d'erreur
     *
     * @param  bool    $logErrors   Enregistrer l'erreur dans un fichier log ?
     * @param  string  $logPath     Chemin du fichier log
     */
    public function init($logErrors = true) {
        @ini_set('display_errors', 'off');
        @ini_set('html_errors', 'off');

        $this->levelReporting = error_reporting();
        $this->logErrors = (bool)$logErrors;

        $caught = false;

        // Gestionnaire d'exceptions
        set_exception_handler(function ($ex) use ($caught) {
            $caught = true;
            $this->setError('Uncaught '.get_class($ex).': '.$ex->getMessage(), $this->getType($ex->getCode()), $ex->getCode(), $ex->getFile(), $ex->getLine(), $this->getTrace(), $caught);
        });

        // Gestionnaire d'erreurs
        set_error_handler(function ($errno, $errstr, $errfile, $errline) use ($caught) {
            $caught = true;
            $this->setError($errstr, $this->getType($errno), $errno, $errfile, $errline, $this->getTrace(), $caught);
        }, $this->levelReporting);

        // Gestionnaire d'erreurs qui auraient échapé au premier gestionnaire
        register_shutdown_function(function () use ($caught) {
            if (!($lastError = error_get_last()))
                return; // Le script s'est déroulé normalement, pas d'erreur

            $this->setError($lastError['message'], $this->getType($lastError['type']), $lastError['type'], $lastError['file'], $lastError['line'], $this->getTrace(), $caught);
        });
    }


    /**
     * Indique si l'utilisateur courant est un développeur
     * Si l'utilisateur est un développeur, les détails de l'erreur seront affichés.
     *
     * @param  ool  $isDev   L'utilisateur est-il un développeur
     */
    public function setDev($isDev = false) {
        $this->showDetError = (bool)$isDev;
    }


    /**
     * Gère une exception qui a été attrapée par l'utilisateur
     *
     * @param  exception  $ex   L'exception attrapée
     */
    public function handle($ex) {
        $this->setError($ex->getMessage(), 'Caught '.get_class($ex), $ex->getCode(), $ex->getFile(), $ex->getLine(), $ex->getTrace(), true);
    }




    /* ************************* PARTIE PRIVEE *************************** */

    /**
     * Récupère la pile d'exécution PHP
     *
     * @return  array  Un array contenant la pile d'exécution
     */
    private function getTrace() {
        if (function_exists('xdebug_get_function_stack'))
            $trace = array_reverse(xdebug_get_function_stack());
        else
            $trace = debug_backtrace();
        array_shift($trace);
        return $trace;
    }


    /**
     * Récupère le type d'erreur en fonction de son code
     *
     * @param   int         Le code de l'erreur
     * @return  string      Le nom du type d'erreur
     */
    private function getType($code) {
        return (isset($this->errorTypes[$code])) ? $this->errorTypes[$code] : 'Unknown error';
    }


    /**
     * Écrit l'erreur attrapée dans le fichier log système
     *
     * @param  array   $error   L'erreur attrapée
     */
    private function writeToLog($error) {
        $message = $error['type'].': '.$error['message'];
        $message .= ' in '.$error['file'].':'.$error['line'];
        $message .= ' ['.$error['guid'].']';

        error_log($message, 4);
    }
    

    /**
     * Fonction qui génère un tableau avec les informations importantes d'une erreur
     * @param string    $message        Message d'erreur
     * @param string    $type           Type de l'erreur
     * @param int       $code           Code de l'erreur
     * @param string    $file           Fichier de l'erreur
     * @param int       $line           Ligne de l'erreur
     * @param array     $trace          Pile d'exécution PHP au moment de l'erreur
     * @param bool      $caught         Erreur gérée par l'utilisateur ?
     */
    private function setError($message, $type, $code, $file, $line, $trace, $caught) {
        $error = array(
            'type'          => $type,
            'code'          => $code,
            'message'       => $message,
            'file'          => $file,
            'line'          => $line,
            'trace'         => $trace,
            'time'          => $_SERVER['REQUEST_TIME'],
            'phpversion'    => 'PHP' . PHP_VERSION . '(' . PHP_OS . ')',
            'uri'           => $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
            'path'          => $_SERVER['SCRIPT_FILENAME'],
            'caught'        => $caught,
            'guid'          => substr(md5(uniqid(rand(), true)), 0, 5),
        );

        if ($this->logErrors)
            $this->writeToLog($error);

        date_default_timezone_set('Europe/Zurich'); 
        if ($this->showDetError)
            $this->showHtmlDetError($error);
        else
            $this->showHtmlAnonError($error);
    }


    /**
     * Affiche une page qui indique qu'il y a eu une erreur avec ses détails complets
     *
     * @param    array  $error   L'erreur qui a été attrapée
     * @echo     Page HTML simple qui informe de l'erreur avec les détails
     */
    private function showHtmlDetError($error) {
?>
<html>
<head>
    <title>Erreur intranet</title>
    <style type="text/css">
*, html, body { margin:0; padding:0; }
::selection { background-color:hotpink; color:white; text-shadow:none; }
body { background-color:#232323; font-family:"ProFontWindows", Monaco, Consolas, Courier, monospace; font-size:12px; color:#FFFFFF; text-shadow:0 1px 0 rgba(0, 0, 0, .1); }
#wrapper { margin:20px 130px; margin-right:15px; margin-bottom:0; }
div.datetime { background-color:#343434; position:absolute; left:0; padding:5px 7px 5px 0; width:95px; text-align:right; color:#969696; }
div.datetime span { line-height:14px; }
span { display:block; line-height:18px; }
span.uri { color:#5b5b5b; }
span.errorcode { color:#cf7272; }
div.errormsg { margin:20px 15px; display:inline-block; }
div.errormsg > span.msg { background-color:#cf7272; color:#532222; border-radius:1999px; padding:0 10px; text-shadow:0 1px 0 rgba(255, 255, 255, .25); box-shadow:0 1px 2px rgba(0, 0, 0, .2); }
div.errormsg > span.linenum { color:#6f6f6f; text-align:right; }
div.software_urgent { color:#424242; display:inline-block; }
div.software_urgent > .ipaddr { color:#FFFFFF; text-align:right; margin-top:10px; text-decoration:none; }
    </style>
</head>
<body>
    <div id="wrapper">
        <div class="datetime">
            <span class="date"><?php echo date("D M Y", $error['time']); ?></span>
            <span class="time"><?php echo date("H:i:s", $error['time']); ?></span>
        </div>
        <span class="uri"><?php echo $error['uri']; ?></span>
        <span class="uri"><?php echo $error['path']; ?></span>
        <span class="errorcode">Error code <?php echo $error['code'],' - ',$error['type']; ?></span>
        <div>
            <div class="errormsg">
            <span class="msg"><?php echo $error['message']; ?> in <?php echo $error['file']; ?></span>
            <span class="linenum">at line <?php echo $error['line']; ?></span>
            </div>
        </div>
        <div>
            <div class="software_urgent">
                <span class="urgent">Logged as <?php echo $error['guid']; ?></span>
                <span class="php"><?php echo $error['phpversion']; ?></span>
                <a class="ipaddr" href="<?php if (isset($_SERVER['referer']) && !empty($_SERVER['referer'])) echo $_SERVER['referer']; else echo 'javascript:history.back()'; ?>">← Retour à la page précédente</a>
            </div>
        </div>
        <div class="software_urgent">
            <pre>Trace : <?php print_r($error['trace']); ?></pre>
        </div>
    </div>
</body>
</html>
<?php
        exit();
    }


    /**
     * Affiche une page qui indique qu'il y a eu une erreur, sans ses détails
     *
     * @param    array  $error   L'erreur qui a été attrapée
     * @echo     Page HTML simple qui informe de l'erreur sans les détails
     */
    private function showHtmlAnonError($error) {
?>
<html>
<head>
    <title>Erreur intranet</title>
    <style type="text/css">
*, html, body { margin:0; padding:0; }
::selection { background-color:hotpink; color:white; text-shadow:none; }
body { background-color:#232323; font-family:"ProFontWindows", Monaco, Consolas, Courier, monospace; font-size:12px; color:#FFFFFF; text-shadow:0 1px 0 rgba(0, 0, 0, .1); }
#wrapper { margin:20px 130px; margin-right:15px; margin-bottom:0; }
div.datetime { background-color:#343434; position:absolute; left:0; padding:5px 7px 5px 0; width:95px; text-align:right; color:#969696; }
div.datetime span { line-height:14px; }
span { display:block; line-height:18px; }
span.uri { color:#5b5b5b; }
span.errorcode { color:#cf7272; }
div.linenum { color:#6f6f6f; margin-top:20px; }
div.software_urgent > .ipaddr { color:#FFFFFF; text-align:right; margin-top:10px; text-decoration:none; }
    </style>
</head>
<body>
    <div id="wrapper">
        <div class="datetime">
            <span class="date"><?php echo date("D M Y", $error['time']); ?></span>
            <span class="time"><?php echo date("H:i:s", $error['time']); ?></span>
        </div>
        <span class="uri"><?php echo $error['uri']; ?></span>
        <span class="errorcode">Erreur de l'intranet</span>
        <div class="linenum">
            Oups… Il y a eu une erreur. <br />
            Vous pouvez en avertir l'IT en précisant le code de l'erreur « <strong><?php echo $error['guid']; ?></strong> ».
        </div>
        <div class="software_urgent">
            <a class="ipaddr" href="<?php if (isset($_SERVER['referer']) && !empty($_SERVER['referer'])) echo $_SERVER['referer']; else echo 'javascript:history.back()'; ?>">← Retour à la page précédente</a>
        </div>
    </div>
</body>
</html>
<?php
        exit();
    }
}