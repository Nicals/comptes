<?php

namespace Comptes\Bundle\CoreBundle\Service;

use Symfony\Component\DependencyInjection\Container;
use Doctrine\ORM\EntityManager;

/**
 * Fournisseur de statistiques.
 */
class StatsProvider
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * Configuration des statistiques,
     *
     * @var array
     */
    protected $configuration;

    /**
     * Constructeur.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        // Injection de dépendances
        $this->container = $container;
        $this->em = $container->get('doctrine')->getManager();

        // Chargement de la configuration
        $configurationLoader = $container->get('comptes_core.configuration.loader');
        $configuration = $configurationLoader->load('stats.yml');
        $this->configuration = $configuration;
    }

    /**
     * Calcule la balance mensuelle moyenne (crédit/débit) de tous les mouvements
     * compris entre deux dates, incluses.
     *
     * @param \DateTime $dateStart Date de début, incluse.
     * @param \DateTime $dateEnd Date de fin, incluse.
     * @return float
     */
    public function getMonthlyBalance($dateStart, $dateEnd)
    {
        // Repositories
        $doctrine = $this->container->get('doctrine');
        $compteRepository = $doctrine->getRepository('ComptesCoreBundle:Compte');
        $mouvementRepository = $doctrine->getRepository('ComptesCoreBundle:Mouvement');

        // Nombre de mois entre les deux dates
        $diff = $dateStart->diff($dateEnd);
        $monthsCount = $diff->y*12 + $diff->m + $diff->d / 30;

        if ($monthsCount < 1)
        {
            $monthsCount = 1;
        }

        // Tous les mouvements entre ces deux dates, et leur balance
        $mouvements = $mouvementRepository->findByDate($dateStart, $dateEnd);
        $balance = $compteRepository->getBalanceByMouvements($mouvements);

        // Balance mensuelle moyenne
        $monthlyBalance = $balance / $monthsCount;

        return $monthlyBalance;
    }
}