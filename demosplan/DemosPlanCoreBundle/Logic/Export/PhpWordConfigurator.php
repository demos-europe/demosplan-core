<?php

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use PhpOffice\PhpWord\ComplexType\ProofState;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Style\Language;

/**
 * Common configurations for PHPWord instances since unfortunately we
 * have several places where we need to obtain new instances.
 */
class PhpWordConfigurator
{
    public static function getPreConfiguredPhpWord(): PhpWord
    {
        $phpWord = new PhpWord();

        // avoid problems with < in statementTexts T3921
        Settings::setOutputEscapingEnabled(true);
        // https://stackoverflow.com/questions/33267654/
        $phpWord->getSettings()->setUpdateFields(true);

        // http://phpword.readthedocs.org/en/latest/index.html
        // https://github.com/PHPOffice/PHPWord

        $configurator = new self();
        $configurator->configureDocumentLanguage($phpWord);

        return $phpWord;
    }

    /**
     * Configures the document language settings and sets the
     * proof states for grammar and spelling to clean. The latter
     * is done to significantly reduce loading times for large documents.
     */
    protected function configureDocumentLanguage(PhpWord $phpWord): void
    {
        $language = new Language();

        $language->setLatin(Language::DE_DE);
        $language->setLangId(Language::DE_DE_ID);

        $phpWord->getSettings()->setThemeFontLang($language);

        $proofState = new ProofState();

        $proofState->setSpelling(ProofState::CLEAN);
        $proofState->setGrammar(ProofState::CLEAN);

        $phpWord->getSettings()->setProofState($proofState);
    }
}
