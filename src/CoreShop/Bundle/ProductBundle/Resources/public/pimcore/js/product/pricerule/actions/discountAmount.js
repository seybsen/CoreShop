/*
 * CoreShop.
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015-2020 Dominik Pfaffenbauer (https://www.pfaffenbauer.at)
 * @license    https://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 *
 */

pimcore.registerNS('coreshop.product.pricerule.actions.discountAmount');
coreshop.product.pricerule.actions.discountAmount = Class.create(coreshop.rules.actions.abstract, {

    type: 'discountAmount',

    getForm: function () {
        var amountValue = 0;
        var currency = null;

        if (this.data) {
            amountValue = this.data.amount / pimcore.globalmanager.get('coreshop.currency.decimal_factor');
            currency = this.data.currency;
        }

        var amount = new Ext.form.NumberField({
            fieldLabel: t('coreshop_action_discountAmount_amount'),
            name: 'amount',
            value: amountValue,
            decimalPrecision: 2
        });

        this.form = new Ext.form.Panel({
            items: [
                amount,
                {
                    xtype: 'coreshop.currency',
                    value: currency
                }
            ]
        });

        return this.form;
    }
});
