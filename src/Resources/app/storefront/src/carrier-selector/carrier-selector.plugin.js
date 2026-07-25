import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';

/**
 * Checkout carrier picker. Fetches the allow-listed carrier options for the
 * current cart, renders them as radio options, and on selection posts the choice
 * to the plugin's storefront endpoint, then reloads so the delivery cost
 * recalculates for the chosen carrier.
 *
 * Mounted on an element carrying [data-kommandhub-carrier-selector]; that
 * element is emitted by the Twig block only under our gate shipping method.
 */
export default class CarrierSelectorPlugin extends Plugin {
    static options = {
        listUrl: '/kommandhub/shipping/carriers',
        selectUrl: '/kommandhub/shipping/select-carrier',
    };

    init() {
        this._client = new HttpClient();
        this._loadCarriers();
    }

    _loadCarriers() {
        this._client.get(this.options.listUrl, (response) => {
            let payload;
            try {
                payload = JSON.parse(response);
            } catch (e) {
                return;
            }
            this._render(payload.carriers || [], payload.selected || null);
        });
    }

    _render(carriers, selected) {
        if (!carriers.length) {
            this.el.innerHTML = '';
            return;
        }

        this.el.innerHTML = carriers
            .map((c) => {
                const checked = c.carrierCode === selected ? 'checked' : '';
                const days = c.estimatedDaysMin ? ` · ${c.estimatedDaysMin}-${c.estimatedDaysMax || c.estimatedDaysMin} days` : '';
                return `
                    <label class="kommandhub-carrier-option">
                        <input type="radio" name="kommandhub-carrier" value="${c.carrierCode}" ${checked}>
                        <span>${c.carrierName || c.serviceName} — ${c.currency} ${c.amount}${days}</span>
                    </label>`;
            })
            .join('');

        this.el.querySelectorAll('input[name="kommandhub-carrier"]').forEach((input) => {
            input.addEventListener('change', (event) => this._select(event.target.value));
        });
    }

    _select(carrierCode) {
        const data = JSON.stringify({ carrierCode });
        this._client.post(this.options.selectUrl, data, () => {
            // Reload so Shopware recalculates the delivery with the chosen carrier.
            window.location.reload();
        });
    }
}
