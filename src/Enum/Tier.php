<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Paliers de classement League of Legends, declares du plus bas au plus haut.
 *
 * L'ordre de declaration EST l'ordre de classement : c'est lui qui permet de
 * filtrer sur un rang minimum. La base ne stocke qu'une chaine composite du
 * type "Diamond II", sans notion d'ordre.
 */
enum Tier: string
{
    case IRON = 'Iron';
    case BRONZE = 'Bronze';
    case SILVER = 'Silver';
    case GOLD = 'Gold';
    case PLATINUM = 'Platinum';
    case EMERALD = 'Emerald';
    case DIAMOND = 'Diamond';
    case MASTER = 'Master';
    case GRANDMASTER = 'Grandmaster';
    case CHALLENGER = 'Challenger';

    public function rank(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    /**
     * Extrait le palier d'un libelle stocke : "Diamond II" donne DIAMOND.
     * Retourne null pour "Unranked" ou toute valeur inconnue.
     */
    public static function fromLabel(?string $label): ?self
    {
        if ($label === null || trim($label) === '') {
            return null;
        }

        $first = strtok(trim($label), ' ');

        foreach (self::cases() as $tier) {
            if (strcasecmp($tier->value, (string) $first) === 0) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Tous les paliers superieurs ou egaux a celui-ci, lui compris.
     *
     * @return list<self>
     */
    public function andAbove(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $tier) => $tier->rank() >= $this->rank()
        ));
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $tier) => $tier->value, self::cases());
    }
}
