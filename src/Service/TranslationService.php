<?php

namespace App\Service;

/** Traduções do CrismaQuest. A versão de produção é pt-BR. */
class TranslationService
{
    private array $translations;

    public function __construct()
    {
        // Mantemos o inglês apenas como fallback técnico para chaves legadas ainda
        // não migradas, mas a interface de produção sempre aplica pt-BR por cima.
        $this->translations = require __DIR__ . '/../../translations/en.php';

        $enModuleDir = __DIR__ . '/../../translations/en';
        if (is_dir($enModuleDir)) {
            foreach (glob($enModuleDir . '/*.php') ?: [] as $moduleFile) {
                $moduleTranslations = require $moduleFile;
                if (is_array($moduleTranslations)) {
                    $this->translations = array_replace($this->translations, $moduleTranslations);
                }
            }
        }

        $ptFile = __DIR__ . '/../../translations/pt.php';
        if (is_readable($ptFile)) {
            $pt = require $ptFile;
            if (is_array($pt)) $this->translations = array_replace($this->translations, $pt);
        }

        // Neutraliza qualquer idioma antigo salvo na sessão do ChronoQuest.
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['lang'] = 'pt';
        }
    }

    public function translate(string $key): string
    {
        return $this->translations[$key] ?? $key;
    }

    public function all(): array
    {
        return $this->translations;
    }
}
