import Plugin from 'src/plugin-system/plugin.class';
import HttpClient from 'src/service/http-client.service';
import PseudoModalUtil from 'src/utility/modal-extension/pseudo-modal.util';

/**
 * Checkout carrier picker. Fetches the allow-listed carrier options for the
 * current cart and renders them inside a Shopware modal.
 */
export default class CarrierSelectorPlugin extends Plugin {
    static options = {
        listUrl: '/kommandhub/shipping/carriers',
        selectUrl: '/kommandhub/shipping/select-carrier',
    };

    init() {
        this._client = new HttpClient();
        this._registerEvents();
    }

    _registerEvents() {
        const trigger = this.el.querySelector('[data-kommandhub-carrier-trigger]');
        if (trigger) {
            trigger.addEventListener('click', this._onClickTrigger.bind(this));
        }
    }

    _onClickTrigger(event) {
        event.preventDefault();
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
            this._openModal(payload.carriers || [], payload.selected || null);
        });
    }

    _openModal(carriers, selected) {
        const content = this._renderModalContent(carriers, selected);
        const modal = new PseudoModalUtil(content);
        modal.open();

        const modalElement = modal.getModal();
        modalElement.querySelectorAll('input[name="kommandhub-carrier"]').forEach((input) => {
            input.addEventListener('change', (event) => this._select(event.target.value));
        });
    }

    _renderModalContent(carriers, selected) {
        if (!carriers.length) {
            return `
                <div class="modal-header">
                    <h5 class="modal-title">Available Shipping Rates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    No shipping rates available.
                </div>
            `;
        }

        const carriersHtml = carriers
            .map((c) => {
                const checked = c.carrierCode === selected ? 'checked' : '';
                const days = c.estimatedDaysMin ? ` · ${c.estimatedDaysMin}-${c.estimatedDaysMax || c.estimatedDaysMin} days` : '';
                const logo = c.logoUrl ? `<img src="${c.logoUrl}" alt="${c.carrierName}" style="height: 25px; max-width: 80px; object-fit: contain; margin-right: 12px;">` : '';
                const descriptionHtml = c.description ? `<div class="small text-muted mt-1">${c.description}</div>` : '';

                return `
                    <div class="kommandhub-carrier-option mb-3 p-2 border rounded">
                        <label class="d-flex align-items-center mb-0" style="cursor: pointer;">
                            <input type="radio" name="kommandhub-carrier" value="${c.carrierCode}" ${checked} class="me-3">
                            <div class="d-flex align-items-center flex-grow-1">
                                ${logo}
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold">${c.carrierName || c.serviceName}</span>
                                        <span class="ms-2">${c.currency} ${c.amount}${days}</span>
                                    </div>
                                    ${descriptionHtml}
                                </div>
                            </div>
                        </label>
                    </div>`;
            })
            .join('');

        return `
            <div class="modal-header">
                <h5 class="modal-title">Available Shipping Rates</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                ${carriersHtml}
            </div>
        `;
    }

    _select(carrierCode) {
        const data = JSON.stringify({ carrierCode });
        this._client.post(this.options.selectUrl, data, () => {
            // Reload so Shopware recalculates the delivery with the chosen carrier.
            window.location.reload();
        });
    }
}
