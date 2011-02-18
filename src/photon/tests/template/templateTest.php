<?php
/* -*- tab-width: 4; indent-tabs-mode: nil; c-basic-offset: 4 -*- */
/*
# ***** BEGIN LICENSE BLOCK *****
# This file is part of Photon, The High Speed PHP Framework.
# Copyright (C) 2010, 2011 Loic d'Anterroches and contributors.
#
# Photon is free software; you can redistribute it and/or modify
# it under the terms of the GNU Lesser General Public License as published by
# the Free Software Foundation; either version 2.1 of the License.
#
# Photon is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
#
# ***** END LICENSE BLOCK ***** */


namespace photon\tests\template\templateTest;

use photon\template\compiler as compiler;


class templateTest extends \PHPUnit_Framework_TestCase
{
    public function testSimpleCompile()
    {
        $compiler = new compiler\Compiler('data-template-simplest.html', 
                                          array(__DIR__));
        $this->assertEquals('Hello World!'."\n", $compiler->compile());
    }

    public function testSimpleVarsCompile()
    {
        $compiler = new compiler\Compiler('data-template-simplevars.html', 
                                          array(__DIR__));
        $this->assertEquals('<?php \photon\template\Renderer::secho($t->_vars->hello); ?> <?php \photon\template\Renderer::secho($t->_vars->world); ?>!'."\n", $compiler->compile());
    }

    public function testSimpleBlock()
    {
        $compiler = new compiler\Compiler('data-template-simpleblock.html', 
                                          array(__DIR__));
        $this->assertEquals('Hello World!'."\n", $compiler->compile());
    }

    public function testExtendSimpleBlock()
    {
        $compiler = new compiler\Compiler('data-template-extend-simpleblock.html', 
                                          array(__DIR__));
        $this->assertEquals('Hello You!'."\n", $compiler->compile());
    }

    public function testExtendWithMods()
    {
        $compiler = new compiler\Compiler('data-template-blockwithmod.html', 
                                          array(__DIR__));
        $this->assertEquals('Hello World! <?php \photon\template\Renderer::secho(\mb_strtoupper($t->_vars->hello));  ?>'."\n\n", $compiler->compile());
    }

    public function testSuperBlock()
    {
        $compiler = new compiler\Compiler('data-template-superblock.html', 
                                          array(__DIR__));
        $this->assertEquals('Hello World! and You!'."\n", $compiler->compile());
        $this->assertEquals('Hello World! and You!'."\n", $compiler->getCompiledTemplate());
    }

    public function testIfElse()
    {
        $compiler = new compiler\Compiler('data-template-ifelse.html', 
                                          array(__DIR__));
        $this->assertEquals('<?php if ($t->_vars->toto): ?>Yo!<?php elseif($t->_vars->titi):?>Yi!<?php else: ?>Boo!<?php endif; ?>'."\n\n", $compiler->compile());
    }

    public function testForeach()
    {
        $compiler = new compiler\Compiler('data-template-foreach.html', 
                                          array(__DIR__));
        $this->assertEquals('<?php foreach ($t->_vars->items as $t->_vars->key => $t->_vars->item):  \photon\template\Renderer::secho($t->_vars->item);  endforeach; ?>'."\n\n", $compiler->compile());
    }

    public function testWhile()
    {
        $compiler = new compiler\Compiler('data-template-while.html', 
                                          array(__DIR__));
        $this->assertEquals('<?php while($t->_vars->true):?>Infinite loop<?php endwhile; ?>'."\n\n", $compiler->compile());
    }

    public function testModifier()
    {
        $compiler = new compiler\Compiler('data-template-modifier.html', 
                                          array(__DIR__));
        $this->assertEquals('<?php \photon\template\Renderer::secho(\photon\template\Modifier::safe($t->_vars->hello)); ?>'."\n\n", $compiler->compile());
        $this->assertEquals('<?php \photonLoadFunction(\'\photon\template\Modifier::safe\'); ?><?php \photon\template\Renderer::secho(\photon\template\Modifier::safe($t->_vars->hello)); ?>'."\n\n", $compiler->getCompiledTemplate());
    }

    public function testBadTemplateFile()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->loadTemplateFile('bad-name-..-.html');
    }

    public function testTemplateFileNotFound()
    {
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__));
    }

    public function testInvalidModifierSyntax()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{$toto|hoo.oo}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testInvalidModifier()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{$toto|hoo}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testModifierWithParam()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{$toto|date:"%b %e, %Y"}';
        $this->assertEquals('<?php \photon\template\Renderer::secho(\photon\template\Modifier::dateFormat($t->_vars->toto,"%b %e, %Y")); ?>', $compiler->compile());
    }

    public function testMissingEndBlock()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{if $toto} do something';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testSimpleTag()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{url \'test_view\'}';
        $this->assertEquals('<?php $_etag = new \photon\template\tag\Url($t); $_etag->start(\'test_view\'); ?>', $compiler->compile());
    }

    public function testRandLDelim()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{ldelim}{rdelim}';
        $this->assertEquals('{}', $compiler->compile());
    }

    public function testInvalidTagSyntax()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{.rl \'test_view\'}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testInvalidFunctionSyntax()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{u.l \'test_view\'}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testLongFunctionTag()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{url($foo)}';
        $this->assertEquals('<?php $_etag = new \photon\template\tag\Url($t); $_etag->start($t->_vars->foo); ?>', $compiler->compile());
    }

    public function testInclude()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{include "data-template-simplest.html"}';
        $this->assertEquals('Hello World!'."\n", $compiler->compile());
    }

    public function testBlockTrans()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{blocktrans}Hello World!{/blocktrans}';
        $this->assertEquals('<?php ob_start(); ?>Hello World!<?php $_bts=ob_get_contents(); ob_end_clean(); echo(__($_bts)); ?>', $compiler->compile());
    }

    public function testBlockTransSubs()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{blocktrans}Hello {$you}!{/blocktrans}';
        $this->assertEquals('<?php ob_start(); ?>Hello %%you%%!<?php $_bts=ob_get_contents(); ob_end_clean(); echo(\photon\translation\Translation::sprintf(__($_bts), array(\'you\' => \photon\template\Renderer::sreturn($t->_vars->you)))); ?>', $compiler->compile());
    }

    public function testMissingEndIf()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{while $true}{if}{/while}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testMissingEndBlockBeforeElse()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{if}{while}{else}{/if}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testMissingEndBlockBeforeElseif()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{if $false}{while}{elseif $true}{/if}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testMissingLiteralStart()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = 'Boo{/literal}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testAssign()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{assign $foo = "bar"}';
        $this->assertEquals('<?php $t->_vars->foo = "bar"; ?>', $compiler->compile());
    }

    public function testLiteral()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{literal}{assign $foo = "bar"}{/literal}';
        $this->assertEquals('{assign $foo = "bar"}', $compiler->compile());
    }

    public function testNoLiteralEnd()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{literal}{assign $foo = "bar"}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testSimpleTrans()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{trans "Me too!"}';
        $this->assertEquals('<?php echo(__("Me too!"));?>', $compiler->compile());
    }

    public function testSimplePluralTrans()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{blocktrans $n}One item.{plural}{$n} items.{/blocktrans}';
        $this->assertEquals('<?php $_btc=$t->_vars->n; ob_start(); ?>One item.<?php $_bts=ob_get_contents(); ob_end_clean(); ob_start(); ?>%%n%% items.<?php $_btp=ob_get_contents(); ob_end_clean(); echo(\photon\translation\Translation::sprintf(_n($_bts, $_btp, $_btc), array(\'n\' => \photon\template\Renderer::sreturn($t->_vars->n))));?>', $compiler->compile());
    }

    public function testComplexPluralTrans()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        // It will not die on the $foo and discard it.
        $compiler->templateContent = '{blocktrans $n, $foo}One item.{plural}{$n} items.{/blocktrans}';
        $this->assertEquals('<?php $_btc=$t->_vars->n; ob_start(); ?>One item.<?php $_bts=ob_get_contents(); ob_end_clean(); ob_start(); ?>%%n%% items.<?php $_btp=ob_get_contents(); ob_end_clean(); echo(\photon\translation\Translation::sprintf(_n($_bts, $_btp, $_btc), array(\'n\' => \photon\template\Renderer::sreturn($t->_vars->n))));?>', $compiler->compile());
    }

    public function testMissingEndBlockBeforeEndBlockTrans()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{blocktrans $n}One item.{plural}{$n} items{if}.{/blocktrans}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testUnAllowedTag()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{foo $n}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testUnAllowedEndTag()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{/foo $n}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testCustomTagStartGen()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false,
                                                'tags' => array('example' => '\\photon\\template\\tag\\Example')));

        $compiler->templateContent = '{example $foo}';
        $this->assertEquals('<?php $_etag = new \photon\template\tag\Example($t); $_etag->start($t->_vars->foo); $example = "foo"; echo("<pre>Start: $example</pre>");?>', $compiler->compile());
    }

    public function testCustomTagEndGen()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false,
                                                'tags' => array('example' => '\\photon\\template\\tag\\Example')));

        $compiler->templateContent = '{example $foo}{/example}';
        $this->assertEquals('<?php $_etag = new \photon\template\tag\Example($t); $_etag->start($t->_vars->foo); $example = "foo"; echo("<pre>Start: $example</pre>"); \photon\template\Renderer::secho($t->_vars->hello); $_etag = new \photon\template\tag\Example($t); $_etag->end(); ?>', $compiler->compile());
    }

    public function testNoEffectTag()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false,
                                                'tags' => array('example' => 'stdClass')));

        $compiler->templateContent = '{example}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        $compiler->compile();
    }

    public function testVarConcat()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{assign $foo = $bar ~ $coffee}';
        $this->assertEquals('<?php $t->_vars->foo = $t->_vars->bar . $t->_vars->coffee; ?>', $compiler->compile());
    }

    public function testVarSubAccess()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{assign $bar = $foo.bar}';
        $this->assertEquals('<?php $t->_vars->bar = $t->_vars->foo->bar; ?>', $compiler->compile());
    }

    public function testVarArrayAccess()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{assign $bar = $foo[$coffee]}';
        $this->assertEquals('<?php $t->_vars->bar = $t->_vars->foo[$t->_vars->coffee]; ?>', $compiler->compile());
    }

    public function testVarBadChar()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{assign $bar = $bar#coffee}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        print $compiler->compile();
    }

    public function testVarBadChar2()
    {
        $compiler = new compiler\Compiler('dummy', 
                                          array(__DIR__), 
                                          array('load' => false));
        $compiler->templateContent = '{$;}';
        $this->setExpectedException('\photon\template\compiler\Exception');
        print $compiler->compile();
    }


}

