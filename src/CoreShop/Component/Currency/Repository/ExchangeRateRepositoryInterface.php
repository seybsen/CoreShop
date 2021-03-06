<?php
/**
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2020 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

namespace CoreShop\Component\Currency\Repository;

use CoreShop\Component\Currency\Model\CurrencyInterface;
use CoreShop\Component\Currency\Model\ExchangeRateInterface;
use CoreShop\Component\Resource\Repository\RepositoryInterface;

interface ExchangeRateRepositoryInterface extends RepositoryInterface
{
    /**
     * @param CurrencyInterface $fromCurrency
     * @param CurrencyInterface $toCurrency
     *
     * @return ExchangeRateInterface|null
     */
    public function findOneWithCurrencyPair(CurrencyInterface $fromCurrency, CurrencyInterface $toCurrency);
}
