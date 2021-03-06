<?php

namespace Comptes\Bundle\CoreBundle\Service;

use Symfony\Component\DependencyInjection\Container;
use Doctrine\ORM\EntityManager;
use Comptes\Bundle\CoreBundle\Entity\Mouvement;

/**
 * Décrit un handler d'import de mouvements.
 *
 * Il doit être surchargé par une classe concrète implémentant la méthode parse.
 * Cette méthode doit parser le fichier d'entrée et remplir différents tableaux
 * de classification des mouvements :
 *      - tous les mouvements,
 *      - les catégorisés,
 *      - les non catégorisés,
 *      - les ambigus,
 *      - ceux à valider.
 */
abstract class MouvementsImportHandler implements ImportHandler
{
    const CATEGORIZED = 0;
    const UNCATEGORIZED = 1;
    const AMBIGUOUS = 2;
    const WAITING = 3;

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * Configuration des imports.
     *
     * @var array
     */
    protected $configuration;

    /**
     * Tous les mouvements parsés.
     *
     * @var array
     */
    private $mouvements;

    /**
     * Tableau de classification.
     *
     * Les mouvements parsés et catégorisés.
     *
     * @var array
     */
    private $categorizedMouvements;

    /**
     * Tableau de classification.
     *
     * Les mouvements parsés et non catégorisés.
     *
     * @var array
     */
    private $uncategorizedMouvements;

    /**
     * Tableau de classification.
     *
     * Les mouvements pour lesquels la catégorie n'a pas pu être formellement
     * déterminée parceque leur description est ambigüe.
     * Par exemple, le mouvement "LOYER JANVIER + EAU" peut correspondre
     * aux catégories "loyer" et "charges".
     * Ces cas nécessitent un choix manuel avant de procéder à leur import.
     *
     * @var array
     */
    private $ambiguousMouvements;

    /**
     * Tableau de classification.
     *
     * Les mouvements pour lesquels une vérification manuelle est nécessaire
     * avant de procéder à leur import, dans le cas d'une suspicion de doublon
     * par exemple.
     *
     * @var array
     */
    private $waitingMouvements;

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
        $configuration = $configurationLoader->load('import.yml');
        $this->configuration = $configuration['handlers']['mouvements'];

        // Tableaux de classification
        $this->mouvements = array();
        $this->categorizedMouvements = array();
        $this->uncategorizedMouvements = array();
        $this->ambiguousMouvements = array();
        $this->waitingMouvements = array();
    }

    /**
     * Ajoute un mouvement à la liste de tous les mouvements parsés.
     *
     * @param Mouvement $mouvement
     * @return self
     */
    public function addMouvement(Mouvement $mouvement)
    {
        $this->mouvements[] = $mouvement;

        return $this;
    }

    /**
     * Récupère tous les mouvements parsés.
     *
     * @return array
     */
    public function getMouvements()
    {
        return $this->mouvements;
    }

    /**
     * Ajoute un mouvement à la liste des mouvements parsés et catégorisés.
     *
     * @param Mouvement $mouvement
     * @return self
     */
    public function addCategorizedMouvement(Mouvement $mouvement)
    {
        $this->categorizedMouvements[] = $mouvement;

        return $this;
    }

    /**
     * Récupère les mouvements parsés et catégorisés.
     *
     * @return array
     */
    public function getCategorizedMouvements()
    {
        return $this->categorizedMouvements;
    }

    /**
     * Ajoute un mouvement à la liste des mouvements parsés et non catégorisés.
     *
     * @param Mouvement $mouvement
     * @return self
     */
    public function addUncategorizedMouvement(Mouvement $mouvement)
    {
        $this->uncategorizedMouvements[] = $mouvement;

        return $this;
    }

    /**
     * Récupère les mouvements parsés et non catégorisés.
     *
     * @return array
     */
    public function getUncategorizedMouvements()
    {
        return $this->uncategorizedMouvements;
    }

    /**
     * Ajoute un mouvement à la liste des mouvements parsés pour lesquels
     * la catégorie n'a pas pu être formellement déterminée.
     *
     * @param Mouvement $mouvement
     * @return self
     */
    public function addAmbiguousMouvement(Mouvement $mouvement)
    {
        $this->ambiguousMouvements[] = $mouvement;

        return $this;
    }

    /**
     * Récupère les mouvements parsés pour lesquels
     * la catégorie n'a pas pu être formellement déterminée.
     *
     * @return array
     */
    public function getAmbiguousMouvements()
    {
        return $this->ambiguousMouvements;
    }

    /**
     * Ajoute un mouvement à la liste des mouvements parsés pour lesquels
     * une vérification manuelle est nécessaire.
     *
     * @param Mouvement $mouvement
     * @return self
     */
    public function addWaitingMouvement(Mouvement $mouvement)
    {
        $this->waitingMouvements[] = $mouvement;

        return $this;
    }

    /**
     * Récupère les mouvements parsés pour lesquels
     * une vérification manuelle est nécessaire.
     *
     * @return array
     */
    public function getWaitingMouvements()
    {
        return $this->waitingMouvements;
    }

    /**
     * Détermine la classification d'un mouvement, parmi :
     *      - self::CATEGORIZED
     *      - self::UNCATEGORIZED
     *      - self::AMBIGUOUS
     *      - self::WAITING
     *
     * @param Mouvement $mouvement
     * @return int
     */
    protected function getClassification(Mouvement $mouvement)
    {
        // Service de catégorisation automatique des mouvements
        $mouvementCategorizer = $this->container->get('comptes_core.mouvement.categorizer');
        $categories = $mouvementCategorizer->getCategories($mouvement);

        if ($categories)
        {
            if (count($categories) > 1)
            {
                $classification = self::AMBIGUOUS;
            }
            else
            {
                $mouvement->setCategorie($categories[0]);

                $classification = self::CATEGORIZED;
            }
        }
        else
        {
            $classification = self::UNCATEGORIZED;
        }

        // Recherche d'un éventuel doublon
        $criteria = array(
            'date' => $mouvement->getDate(),
            'compte' => $mouvement->getCompte(),
            'montant' => $mouvement->getMontant()
        );
        $mouvementRepository = $this->em->getRepository('ComptesCoreBundle:Mouvement');
        $similarMouvement = $mouvementRepository->findOneBy($criteria);

        if ($similarMouvement !== null)
        {
            $classification = self::WAITING;
        }

        return $classification;
    }

    /**
     * Insère le mouvement dans les tableaux de classification.
     *
     * @param Mouvement $mouvement
     * @param int $classification
     */
    protected function classify(Mouvement $mouvement, $classification)
    {
        // Classification du mouvement
        switch ($classification)
        {
            case self::CATEGORIZED:
                $this->addCategorizedMouvement($mouvement);
                break;
            case self::UNCATEGORIZED:
                $this->addUncategorizedMouvement($mouvement);
                break;
            case self::AMBIGUOUS:
                $this->addAmbiguousMouvement($mouvement);
                break;
            case self::WAITING:
                $this->addWaitingMouvement($mouvement);
                break;
        }

        $this->addMouvement($mouvement);
    }
}