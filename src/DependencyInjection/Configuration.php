<?php

declare(strict_types=1);

namespace Aubes\CSPBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     *
     * @psalm-suppress UndefinedMethod
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('csp');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('report_logger')
                    ->children()
                        ->scalarNode('logger_id')->end()
                        ->scalarNode('level')->end()
                    ->end()
                ->end()
                ->scalarNode('default_group')->defaultNull()->end()
                ->scalarNode('auto_default')->defaultFalse()->end()
                ->arrayNode('groups')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->arrayPrototype()
                        ->children()
                            ->booleanNode('report_only')->defaultFalse()->end()

                            ->arrayNode('reporting')
                                ->children()
                                    ->integerNode('max_age')->end()
                                    ->scalarNode('group_name')->defaultNull()->end()
                                    ->arrayNode('endpoints')
                                        ->scalarPrototype()->end()
                                    ->end()
                                    ->booleanNode('backward_compatibility')->defaultFalse()->end()
                                ->end()
                            ->end()

                            ->arrayNode('policies')
                                ->isRequired()
                                ->arrayPrototype()
                                    ->scalarPrototype()->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
