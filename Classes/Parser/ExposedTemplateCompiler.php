<?php
namespace FluidTYPO3\Builder\Parser;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Claus Due <claus@namelesscoder.net>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

use TYPO3Fluid\Fluid\Core\Compiler\TemplateCompiler;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;

/**
 * Class ExposedTemplateCompiler
 *
 * Replacement TemplateCompiler intended solely for analysis
 * purposes. Does not work to compile templates normally!
 */
class ExposedTemplateCompiler extends TemplateCompiler
{
    /**
     * Overridden "store" method does not store - instead, it returns
     * the compiled result.
     *
     * @param string $identifier
     * @param ParsingState $parsingState
     * @return string
     */
    public function store($identifier, ParsingState $parsingState)
    {
        $identifier = $this->sanitizeIdentifier($identifier);
        $this->variableCounter = 0;
        $generatedRenderFunctions = '';

        if ($parsingState->getVariableContainer()->exists('sections')) {
            $sections = $parsingState->getVariableContainer()->get('sections');
            // @todo refactor to $parsedTemplate->getSections()
            foreach ($sections as $sectionName => $sectionRootNode) {
                $generatedRenderFunctions .= $this->generateCodeForSection(
                    $this->nodeConverter->convertListOfSubNodes($sectionRootNode),
                    'section_' . sha1($sectionName),
                    'section ' . $sectionName
                );
            }
        }
        $generatedRenderFunctions .= $this->generateCodeForSection(
            $this->nodeConverter->convertListOfSubNodes($parsingState->getRootNode()),
            'render',
            'Main Render function'
        );
        if ($parsingState->hasLayout() && method_exists($parsingState, 'getLayoutNameNode')) {
            $convertedLayoutNameNode = $this->nodeConverter->convert($parsingState->getLayoutNameNode());
        } elseif ($parsingState->hasLayout() && method_exists($parsingState, 'getLayoutName')) {
            $convertedLayoutNameNode = $parsingState->getLayoutName($this->renderingContext);
        } else {
            $convertedLayoutNameNode = ['initialization' => '', 'execution' => 'NULL'];
        }

        $classDefinition = 'class FluidCache_' . $identifier .
            ' extends \\TYPO3\\CMS\\Fluid\\Core\\Compiler\\AbstractCompiledTemplate';

        $templateCode = <<<EOD
%s {

public function getVariableContainer() {
	// @todo
	return new \TYPO3\CMS\Fluid\Core\ViewHelper\TemplateVariableContainer();
}
public function getLayoutName(\TYPO3\CMS\Fluid\Core\Rendering\RenderingContextInterface \$renderingContext) {
\$currentVariableContainer = \$renderingContext->getTemplateVariableContainer();
%s
return %s;
}
public function hasLayout() {
return %s;
}

%s

}
EOD;
        return sprintf(
            $templateCode,
            $classDefinition,
            $convertedLayoutNameNode['initialization'],
            $convertedLayoutNameNode['execution'],
            ($parsingState->hasLayout() ? 'TRUE' : 'FALSE'),
            $generatedRenderFunctions
        );
    }
}
