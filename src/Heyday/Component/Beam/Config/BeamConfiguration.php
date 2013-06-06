<?php

namespace Heyday\Component\Beam\Config;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class BeamConfiguration
 * @package Heyday\Component\Beam\Config
 */
class BeamConfiguration extends Configuration implements ConfigurationInterface
{
    /**
     * @var array
     */
    protected $applications = array(
        '_base'        => array(
            '*~',
            '.DS_Store',
            '.gitignore',
            '.mergesources.yml',
            'README.md',
            'composer.json',
            'composer.lock',
            'deploy.json',
            'beam.json',
            'deploy.properties',
            'sftp-config.json',
            'checksums.json*',
            '/access-logs/',
            '/cgi-bin/',
            '/.idea/',
            '.svn/',
            '.git/'
        ),
        'gear'         => array(
            '/images/repository/'
        ),
        'silverstripe' => array(
            '/assets/',
            '/silverstripe-cache/',
            '/assets-generated/',
            '/cache-include/cache/',
            '/heyday-cacheinclude/cache/',
            '/silverstripe-cacheinclude/cache/'
        ),
        'symfony'      => array(
            '/cache/',
            '/data/lucene/',
            '/config/log/',
            '/data/lucene/',
            '/lib/form/base/',
            '/lib/model/map/',
            '/lib/model/om/',
            '/log/',
            '/web/uploads/'
        ),
        'wordpress'    => array(
            'wp-content/uploads/'
        ),
        'zf'           => array(
            '/www/uploads/',
            '/web/uploads/'
        )
    );
    /**
     * @var array
     */
    protected $phases = array(
        'pre',
        'post'
    );
    /**
     * @var array
     */
    protected $locations = array(
        'local',
        'target'
    );
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('beam');

        $self = $this;

        $rootNode
            ->children()
                ->arrayNode('servers')
                    ->isRequired()
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->scalarNode('user')->isRequired()->end()
                            ->scalarNode('host')->isRequired()->end()
                            ->scalarNode('webroot')
                                ->isRequired()
                                ->validate()
                                    ->always(
                                        function ($v) {
                                            return rtrim($v, '/');
                                        }
                                    )
                                ->end()
                            ->end()
                            ->scalarNode('branch')->end()
                            ->scalarNode('password')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('commands')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('command')->isRequired()->end()
                            ->scalarNode('phase')
                                ->isRequired()
                                ->validate()
                                    ->ifNotInArray($this->phases)
                                    ->thenInvalid(
                                        'Phase "%s" is not valid, options are: ' .
                                            $this->getFormattedOptions($this->phases)
                                    )
                                ->end()
                            ->end()
                            ->scalarNode('location')
                                ->isRequired()
                                ->validate()
                                    ->ifNotInArray($this->locations)
                                    ->thenInvalid(
                                        'Location "%s" is not valid, options are: ' .
                                            $this->getFormattedOptions($this->locations)
                                    )
                                ->end()
                            ->end()
                            ->arrayNode('servers')
                                ->prototype('scalar')->end()
                            ->end()
                            ->scalarNode('required')->defaultFalse()->end()
                            ->scalarNode('tag')->defaultFalse()->end()
                            ->scalarNode('tty')->defaultFalse()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('exclude')
                    ->children()
                        ->arrayNode('applications')
                            ->prototype('scalar')
                                ->validate()
                                    ->ifNotInArray(array_keys($this->applications))
                                    ->thenInvalid(
                                        'Application "%s" is not valid, options are: ' .
                                            $this->getFormattedOptions(array_keys($this->applications))
                                    )
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('patterns')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->always(
                            function ($v) use ($self) {
                                return $self->buildExcludes($v);
                            }
                        )
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->always(
                    function ($v) use ($self) {
                        foreach ($v['commands'] as $commandName => $command) {
                            foreach ($command['servers'] as $server) {
                                if (!isset($v['servers'][$server])) {
                                    throw new \InvalidArgumentException(
                                        "Command \"{$commandName}\" references an invalid server, options are: " .
                                            $self->getFormattedOptions(array_keys($v['servers']))
                                    );
                                }
                            }
                        }

                        if (!isset($v['exclude'])) {
                            $v['exclude'] = $self->buildExcludes(false);
                        }

                        return $v;
                    }
                )
            ->end();

        return $treeBuilder;
    }
    /**
     * @param $value
     * @return array
     */
    public function buildExcludes($value)
    {
        $excludes = array();

        if (!$value) {
            $value = array(
                'applications' => array(),
                'patterns'     => array()
            );
        }

        if (!in_array('_base', $value['applications'])) {
            $value['applications'][] = '_base';
        }

        foreach ($value['applications'] as $application) {
            $excludes = array_merge($excludes, $this->applications[$application]);
        }

        return array_merge($excludes, $value['patterns']);
    }
}