import '../../vendor/masmerise/livewire-toaster/resources/js';

document.addEventListener('alpine:init', () => {
    const { Alpine } = window;

    if (! Alpine) {
        return;
    }

    /**
     * Shared custom-amount pricing estimate (one server round-trip for rate, client multiplies as user types).
     *
     * @param {object} ctx Alpine component `this` with amountInputStr, rate, serverError, amountMin, amountMax, amountStep, messages
     */
    const customAmountEstimate = {
        round2(value) {
            return Math.round((Number(value) + Number.EPSILON) * 100) / 100;
        },

        digitsParsed(ctx) {
            const n = parseInt(String(ctx.amountInputStr).replace(/\D/g, ''), 10);

            return Number.isNaN(n) ? 0 : n;
        },

        amountInvalidReason(ctx) {
            const a = customAmountEstimate.digitsParsed(ctx);
            if (a < 1) {
                return 'empty';
            }
            if (ctx.amountMin !== null && ctx.amountMin !== undefined && a < ctx.amountMin) {
                return 'below_min';
            }
            if (ctx.amountMax !== null && ctx.amountMax !== undefined && a > ctx.amountMax) {
                return 'above_max';
            }
            if (ctx.amountStep > 1 && a % ctx.amountStep !== 0) {
                return 'bad_step';
            }

            return null;
        },

        amountValid(ctx) {
            return customAmountEstimate.amountInvalidReason(ctx) === null;
        },

        estimatedFinal(ctx) {
            if (ctx.serverError) {
                return null;
            }
            if (! customAmountEstimate.amountValid(ctx)) {
                return null;
            }

            if (ctx.rate === null || ctx.rate === undefined) {
                return null;
            }

            const raw = customAmountEstimate.digitsParsed(ctx) * Number(ctx.rate);

            return customAmountEstimate.round2(raw);
        },

        estimateHintText(ctx) {
            if (ctx.serverError) {
                return '';
            }
            if (customAmountEstimate.estimatedFinal(ctx) !== null) {
                return '';
            }
            const msgs = ctx.messages ?? {};
            const reason = customAmountEstimate.amountInvalidReason(ctx);
            if (reason === 'empty') {
                return msgs.enter_amount ?? '—';
            }
            if (reason === 'below_min') {
                return msgs.below_min ?? '—';
            }
            if (reason === 'above_max') {
                return msgs.above_max ?? '—';
            }
            if (reason === 'bad_step') {
                return msgs.bad_step ?? '—';
            }
            if (customAmountEstimate.amountValid(ctx) && (ctx.rate === null || ctx.rate === undefined)) {
                return msgs.price_unavailable ?? '—';
            }

            return '—';
        },

        estimateCardEmphasis(ctx) {
            if (ctx.serverError) {
                return 'error';
            }
            if (customAmountEstimate.estimatedFinal(ctx) !== null) {
                return 'success';
            }
            const r = customAmountEstimate.amountInvalidReason(ctx);
            if (r === 'below_min' || r === 'above_max' || r === 'bad_step') {
                return 'attention';
            }
            if (customAmountEstimate.amountValid(ctx) && (ctx.rate === null || ctx.rate === undefined)) {
                return 'attention';
            }

            return 'neutral';
        },

        formatMoney(value) {
            if (value === null || value === undefined) {
                return '—';
            }

            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(value);
        },

        formatPerUnitRate(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) {
                return '—';
            }

            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 6,
            }).format(Number(value));
        },
    };

    Alpine.data('buyNowCustomAmountEstimate', (config) => ({
        amountInputStr: config.amountInputStr ?? '',
        rate: config.rate,
        serverError: config.serverError ?? null,
        amountMin: config.amountMin ?? null,
        amountMax: config.amountMax ?? null,
        amountStep: Math.max(1, Number(config.amountStep ?? 1)),
        maskDec: config.maskDec ?? '.',
        maskThousands: config.maskThousands ?? ',',
        messages: config.messages ?? {},

        get digitsParsed() {
            return customAmountEstimate.digitsParsed(this);
        },

        get amountInvalidReason() {
            return customAmountEstimate.amountInvalidReason(this);
        },

        get amountValid() {
            return customAmountEstimate.amountValid(this);
        },

        get estimatedFinal() {
            return customAmountEstimate.estimatedFinal(this);
        },

        get estimateHintText() {
            return customAmountEstimate.estimateHintText(this);
        },

        get estimateCardEmphasis() {
            return customAmountEstimate.estimateCardEmphasis(this);
        },

        formatMoney(value) {
            return customAmountEstimate.formatMoney(value);
        },
    }));

    Alpine.data('packageOverlayCustomAmountRow', (config) => ({
        product: config.product,
        amountInputStr: config.amountInputStr ?? '',
        rate: config.rate,
        serverError: config.serverError ?? null,
        amountMin: config.amountMin ?? null,
        amountMax: config.amountMax ?? null,
        amountStep: Math.max(1, Number(config.amountStep ?? 1)),
        maskDec: config.maskDec ?? '.',
        maskThousands: config.maskThousands ?? ',',
        messages: config.messages ?? {},
        qty: 1,

        init() {
            if (config.initialAmount != null && config.initialAmount !== undefined) {
                this.amountInputStr = Alpine.store('cart').formatGroupedIntegerForAmount(Number(config.initialAmount));
            }
        },

        get digitsParsed() {
            return customAmountEstimate.digitsParsed(this);
        },

        get amountInvalidReason() {
            return customAmountEstimate.amountInvalidReason(this);
        },

        get amountValid() {
            return customAmountEstimate.amountValid(this);
        },

        get estimatedFinal() {
            return customAmountEstimate.estimatedFinal(this);
        },

        get estimateHintText() {
            return customAmountEstimate.estimateHintText(this);
        },

        get estimateCardEmphasis() {
            return customAmountEstimate.estimateCardEmphasis(this);
        },

        formatMoney(value) {
            return customAmountEstimate.formatMoney(value);
        },

        formatPerUnitRate(value) {
            return customAmountEstimate.formatPerUnitRate(value);
        },
    }));

    Alpine.store('cart', {
        storageKey: 'karman.cart.v1',
        hydrated: false,
        items: [],
        validationMessages: {},
        serverRequirementErrors: {},
        customAmountErrors: {},
        /** When false, empty required package fields do not show inline errors until checkout is attempted. */
        showCartRequirementErrors: false,
        formatter: new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            maximumFractionDigits: 2,
        }),
        addToCartMessageTemplate: 'Added :name to cart',

        init() {
            if (this.hydrated) {
                return;
            }

            this.items = this.read();
            if (typeof window.__addToCartMessageTemplate !== 'undefined') {
                this.addToCartMessageTemplate = window.__addToCartMessageTemplate;
            }
            this.hydrated = true;
        },
        formatGroupedIntegerForAmount(value) {
            const thousands = window.Laravel?.amountIntegerMask?.thousands ?? ',';
            const raw = Math.max(0, parseInt(String(value ?? '').replace(/\D/g, ''), 10) || 0);
            const digits = String(raw);

            return digits.replace(/\B(?=(\d{3})+(?!\d))/g, thousands);
        },
        setValidationMessages(messages) {
            this.validationMessages = messages ?? {};
        },
        formatMessage(template, params = {}) {
            if (! template) {
                return '';
            }

            return Object.entries(params).reduce((message, [key, value]) => {
                return message.replace(`:${key}`, value);
            }, template);
        },
        read() {
            try {
                const parsed = JSON.parse(localStorage.getItem(this.storageKey) ?? '[]');

                if (! Array.isArray(parsed)) {
                    return [];
                }

                return parsed.map((item) => {
                    if (item?.amount_mode !== 'custom') {
                        return item;
                    }

                    const requested = item.requested_amount ?? (item.custom_amount_min ?? 1);
                    const linePrice = Number(item.price ?? 0);
                    let customUnitRate = item.custom_unit_rate;
                    if (
                        (customUnitRate === null || customUnitRate === undefined)
                        && linePrice > 0
                        && requested > 0
                    ) {
                        customUnitRate = Math.round((linePrice / requested) * 1e8) / 1e8;
                    }

                    return {
                        ...item,
                        quantity: 1,
                        custom_amount_step: Math.max(1, parseInt(item.custom_amount_step ?? 1, 10)),
                        requested_amount: requested,
                        requested_amount_input: this.formatGroupedIntegerForAmount(requested),
                        custom_unit_rate: customUnitRate,
                    };
                });
            } catch (error) {
                return [];
            }
        },
        persist() {
            const serializable = this.items.map((item) => {
                if (item?.requested_amount_input === undefined) {
                    return item;
                }

                const { requested_amount_input: _strip, ...rest } = item;

                return rest;
            });

            localStorage.setItem(this.storageKey, JSON.stringify(serializable));
        },
        add(product, quantity = 1) {
            if (! product?.id) {
                return;
            }

            const amountMode = product.amount_mode ?? 'fixed';
            const normalizedQuantity = Math.max(1, parseInt(quantity ?? 1, 10));
            const existing = this.items.find((item) => item.id === product.id);

            if (existing) {
                if (amountMode !== 'custom') {
                    existing.quantity += normalizedQuantity;
                }
            } else {
                const requirementsSchema = Array.isArray(product.requirements_schema)
                    ? product.requirements_schema
                    : [];
                const requirements = {};
                const normalizedStep = Math.max(1, parseInt(product.custom_amount_step ?? 1, 10));
                const normalizedMin = product.custom_amount_min !== null && product.custom_amount_min !== undefined
                    ? Math.max(1, parseInt(product.custom_amount_min, 10))
                    : null;
                const normalizedMax = product.custom_amount_max !== null && product.custom_amount_max !== undefined
                    ? Math.max(1, parseInt(product.custom_amount_max, 10))
                    : null;
                const defaultRequestedAmount = amountMode === 'custom'
                    ? (normalizedMin ?? normalizedStep)
                    : null;

                requirementsSchema.forEach((requirement) => {
                    if (requirement?.key) {
                        requirements[requirement.key] = '';
                    }
                });

                const linePrice = Number(product.price ?? 0);
                const customUnitRate =
                    amountMode === 'custom'
                    && defaultRequestedAmount !== null
                    && defaultRequestedAmount > 0
                    && linePrice > 0
                        ? Math.round((linePrice / defaultRequestedAmount) * 1e8) / 1e8
                        : undefined;

                this.items.push({
                    id: product.id,
                    name: product.name,
                    price: linePrice,
                    discount_amount: Number(product.discount_amount ?? 0),
                    tier_name: product.tier_name ?? null,
                    image: product.image,
                    href: product.href,
                    quantity: amountMode === 'custom' ? 1 : normalizedQuantity,
                    amount_mode: amountMode,
                    requested_amount: defaultRequestedAmount,
                    requested_amount_input:
                        amountMode === 'custom'
                            ? this.formatGroupedIntegerForAmount(defaultRequestedAmount)
                            : undefined,
                    custom_unit_rate: customUnitRate,
                    amount_unit_label: product.amount_unit_label ?? null,
                    custom_amount_min: normalizedMin,
                    custom_amount_max: normalizedMax,
                    custom_amount_step: normalizedStep,
                    package_id: product.package_id ?? null,
                    requirements,
                    requirements_schema: requirementsSchema,
                });
            }

            this.persist();

            if (product?.name) {
                const message = this.formatMessage(this.addToCartMessageTemplate, { name: product.name });
                window.Toaster?.success(message);
                window.dispatchEvent(new CustomEvent('cart-item-added', {
                    detail: {
                        name: product.name,
                    },
                }));
            }
        },
        setServerRequirementError(payload) {
            const productId = payload?.productId ?? payload?.product_id ?? null;
            const key = payload?.key ?? null;

            if (! productId || ! key) {
                return;
            }

            if (! this.serverRequirementErrors[productId]) {
                this.serverRequirementErrors[productId] = {};
            }

            if (! payload.message) {
                delete this.serverRequirementErrors[productId][key];
                return;
            }

            this.serverRequirementErrors[productId][key] = payload.message;
        },
        setServerRequirementErrors(errors) {
            this.serverRequirementErrors = errors ?? {};
        },
        clearServerRequirementError(productId, key) {
            if (! this.serverRequirementErrors?.[productId]?.[key]) {
                return;
            }

            delete this.serverRequirementErrors[productId][key];
        },
        updateRequirement(id, key, value) {
            const item = this.items.find((entry) => entry.id === id);

            if (! item) {
                return;
            }

            if (! item.requirements) {
                item.requirements = {};
            }

            item.requirements[key] = value;
            this.clearServerRequirementError(id, key);
            this.persist();
        },
        isEmptyValue(value) {
            if (value === null || value === undefined) {
                return true;
            }

            return String(value).trim() === '';
        },
        parseValidationRules(requirement) {
            const rules = [];

            if (requirement?.is_required) {
                rules.push('required');
            }

            if (requirement?.type === 'number') {
                rules.push('numeric');
            }

            if (typeof requirement?.validation_rules === 'string' && requirement.validation_rules.trim() !== '') {
                const extraRules = requirement.validation_rules
                    .split('|')
                    .map((rule) => rule.trim())
                    .filter((rule) => rule !== '');

                rules.push(...extraRules);
            }

            return Array.from(new Set(rules));
        },
        isRequirementMissing(item, requirement) {
            const rules = this.parseValidationRules(requirement);

            if (! rules.includes('required')) {
                return false;
            }

            const value = item?.requirements?.[requirement?.key];

            return this.isEmptyValue(value);
        },
        isRequirementInvalid(item, requirement) {
            if (this.isRequirementMissing(item, requirement)) {
                return false;
            }

            const rules = this.parseValidationRules(requirement).filter((rule) => rule !== 'required');
            const value = item?.requirements?.[requirement?.key];

            if (this.isEmptyValue(value)) {
                return false;
            }

            const stringValue = String(value);
            const numericValue = Number(value);
            const isNumeric = ! Number.isNaN(numericValue);
            const hasNumericRule = rules.includes('numeric') || requirement?.type === 'number';

            for (const rule of rules) {
                if (rule === 'string') {
                    continue;
                }

                if (rule === 'numeric' && ! isNumeric) {
                    return true;
                }

                if (rule.startsWith('min_digits:')) {
                    const limit = Number(rule.split(':')[1]);
                    const digits = stringValue.replace(/\D/g, '').length;
                    if (! Number.isNaN(limit) && digits < limit) {
                        return true;
                    }
                }

                if (rule.startsWith('max_digits:')) {
                    const limit = Number(rule.split(':')[1]);
                    const digits = stringValue.replace(/\D/g, '').length;
                    if (! Number.isNaN(limit) && digits > limit) {
                        return true;
                    }
                }

                if (rule.startsWith('min:')) {
                    const limit = Number(rule.split(':')[1]);
                    if (! Number.isNaN(limit)) {
                        const result = hasNumericRule
                            ? numericValue < limit
                            : stringValue.length < limit;
                        if (result) {
                            return true;
                        }
                    }
                }

                if (rule.startsWith('max:')) {
                    const limit = Number(rule.split(':')[1]);
                    if (! Number.isNaN(limit)) {
                        const result = hasNumericRule
                            ? numericValue > limit
                            : stringValue.length > limit;
                        if (result) {
                            return true;
                        }
                    }
                }

                if (rule.startsWith('in:')) {
                    const allowed = rule
                        .slice(3)
                        .split(',')
                        .map((value) => value.trim());
                    if (! allowed.includes(stringValue)) {
                        return true;
                    }
                }

                if (rule.startsWith('regex:') || rule.startsWith('not_regex:')) {
                    const isNot = rule.startsWith('not_regex:');
                    const raw = rule.replace(/^not_regex:|^regex:/, '');
                    let regex = null;

                    try {
                        const match = raw.match(/^\/(.+)\/([a-z]*)$/i);
                        regex = match ? new RegExp(match[1], match[2]) : new RegExp(raw);
                    } catch (error) {
                        continue;
                    }

                    const matches = regex.test(stringValue);

                    if (isNot ? matches : ! matches) {
                        return true;
                    }
                }
            }

            return false;
        },
        getRequirementError(item, requirement) {
            const fieldLabel = requirement?.label || requirement?.key || '';
            const serverError = this.serverRequirementErrors?.[item?.id]?.[requirement?.key];

            if (serverError) {
                return serverError;
            }

            if (this.isRequirementMissing(item, requirement)) {
                if (! this.showCartRequirementErrors) {
                    return null;
                }

                return this.formatMessage(
                    this.validationMessages.required ?? ':field is required.',
                    { field: fieldLabel }
                );
            }

            if (! this.isRequirementInvalid(item, requirement)) {
                return null;
            }

            const rules = this.parseValidationRules(requirement).filter((rule) => rule !== 'required');
            const value = item?.requirements?.[requirement?.key];
            const stringValue = String(value ?? '');
            const numericValue = Number(value);
            const isNumeric = ! Number.isNaN(numericValue);
            const hasNumericRule = rules.includes('numeric') || requirement?.type === 'number';

            if (rules.includes('numeric') && ! isNumeric) {
                return this.formatMessage(
                    this.validationMessages.numeric ?? ':field must be a number.',
                    { field: fieldLabel }
                );
            }

            for (const rule of rules) {
                if (rule.startsWith('min_digits:')) {
                    const limit = Number(rule.split(':')[1]);
                    const digits = stringValue.replace(/\D/g, '').length;
                    if (! Number.isNaN(limit) && digits < limit) {
                        return this.formatMessage(
                            this.validationMessages.min_digits ?? ':field must be at least :min digits.',
                            { field: fieldLabel, min: limit }
                        );
                    }
                }

                if (rule.startsWith('max_digits:')) {
                    const limit = Number(rule.split(':')[1]);
                    const digits = stringValue.replace(/\D/g, '').length;
                    if (! Number.isNaN(limit) && digits > limit) {
                        return this.formatMessage(
                            this.validationMessages.max_digits ?? ':field must be at most :max digits.',
                            { field: fieldLabel, max: limit }
                        );
                    }
                }

                if (rule.startsWith('min:')) {
                    const limit = Number(rule.split(':')[1]);
                    if (! Number.isNaN(limit)) {
                        const isInvalid = hasNumericRule ? numericValue < limit : stringValue.length < limit;
                        if (isInvalid) {
                            return hasNumericRule
                                ? this.formatMessage(
                                    this.validationMessages.min_value ?? ':field must be at least :min.',
                                    { field: fieldLabel, min: limit }
                                )
                                : this.formatMessage(
                                    this.validationMessages.min_chars ?? ':field must be at least :min characters.',
                                    { field: fieldLabel, min: limit }
                                );
                        }
                    }
                }

                if (rule.startsWith('max:')) {
                    const limit = Number(rule.split(':')[1]);
                    if (! Number.isNaN(limit)) {
                        const isInvalid = hasNumericRule ? numericValue > limit : stringValue.length > limit;
                        if (isInvalid) {
                            return hasNumericRule
                                ? this.formatMessage(
                                    this.validationMessages.max_value ?? ':field must be at most :max.',
                                    { field: fieldLabel, max: limit }
                                )
                                : this.formatMessage(
                                    this.validationMessages.max_chars ?? ':field must be at most :max characters.',
                                    { field: fieldLabel, max: limit }
                                );
                        }
                    }
                }

                if (rule.startsWith('in:')) {
                    const allowed = rule
                        .slice(3)
                        .split(',')
                        .map((value) => value.trim());
                    if (! allowed.includes(stringValue)) {
                        return this.formatMessage(
                            this.validationMessages.in ?? ':field must be one of: :values.',
                            { field: fieldLabel, values: allowed.join(', ') }
                        );
                    }
                }

                if (rule.startsWith('regex:') || rule.startsWith('not_regex:')) {
                    return this.formatMessage(
                        this.validationMessages.invalid_format ?? ':field has an invalid format.',
                        { field: fieldLabel }
                    );
                }
            }

            return this.formatMessage(
                this.validationMessages.invalid_value ?? ':field is invalid.',
                { field: fieldLabel }
            );
        },
        applyRequirementsSchema(requirementsByProduct) {
            if (! requirementsByProduct) {
                return;
            }

            this.items.forEach((item) => {
                const schema = requirementsByProduct[item.id] ?? [];

                if (! Array.isArray(schema) || schema.length === 0) {
                    return;
                }

                item.requirements_schema = schema;
                item.requirements = item.requirements ?? {};

                schema.forEach((requirement) => {
                    if (requirement?.key && item.requirements[requirement.key] === undefined) {
                        item.requirements[requirement.key] = '';
                    }
                });
            });

            this.persist();
        },
        increment(id) {
            const item = this.items.find((entry) => entry.id === id);

            if (! item) {
                return;
            }

            if (item.amount_mode === 'custom') {
                item.quantity = 1;
                return;
            }

            item.quantity += 1;
            this.persist();
        },
        decrement(id) {
            const item = this.items.find((entry) => entry.id === id);

            if (! item) {
                return;
            }

            if (item.amount_mode === 'custom') {
                item.quantity = 1;
                return;
            }

            if (item.quantity <= 1) {
                this.remove(id);
                return;
            }

            item.quantity -= 1;
            this.persist();
        },
        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
            delete this.serverRequirementErrors[id];
            delete this.customAmountErrors[id];
            this.persist();
        },
        clear() {
            this.items = [];
            this.serverRequirementErrors = {};
            this.customAmountErrors = {};
            this.showCartRequirementErrors = false;
            this.persist();
        },
        getCustomAmountError(item) {
            return this.customAmountErrors[item?.id] ?? null;
        },
        parseAmountDigitsFromInput(value) {
            const n = parseInt(String(value ?? '').replace(/\D/g, ''), 10);

            return Number.isNaN(n) ? null : n;
        },

        customAmountPreviewValid(item, amount) {
            if (amount === null || amount < 1) {
                return false;
            }

            const min = item.custom_amount_min;
            const max = item.custom_amount_max;
            const step = Math.max(1, parseInt(item.custom_amount_step ?? 1, 10));

            if (min !== null && min !== undefined && amount < min) {
                return false;
            }

            if (max !== null && max !== undefined && amount > max) {
                return false;
            }

            if (step > 1 && amount % step !== 0) {
                return false;
            }

            return true;
        },

        lineTotalForItem(item) {
            if (item?.amount_mode !== 'custom') {
                return Number(item.price ?? 0) * Number(item.quantity ?? 0);
            }

            const rate = Number(item.custom_unit_rate);
            if (Number.isNaN(rate) || rate <= 0) {
                return Number(item.price ?? 0);
            }

            const previewAmount = this.parseAmountDigitsFromInput(item.requested_amount_input ?? '');
            if (previewAmount !== null && this.customAmountPreviewValid(item, previewAmount)) {
                return Math.round(previewAmount * rate * 100) / 100;
            }

            return Number(item.price ?? 0);
        },

        /**
         * Resolved $/unit for custom-amount lines (for display). Line totals use 2dp; per-unit can be very small.
         */
        customUnitRateForDisplay(item) {
            if (item?.amount_mode !== 'custom') {
                return null;
            }

            const fromStored = Number(item.custom_unit_rate);
            if (! Number.isNaN(fromStored) && fromStored > 0) {
                return fromStored;
            }

            const line = this.lineTotalForItem(item);
            const preview = this.parseAmountDigitsFromInput(item.requested_amount_input ?? '');
            if (preview !== null && preview > 0 && this.customAmountPreviewValid(item, preview) && line > 0) {
                return line / preview;
            }

            const price = Number(item.price ?? 0);
            const ra = Number(item.requested_amount ?? 0);
            if (price > 0 && ra > 0) {
                return price / ra;
            }

            return null;
        },

        /** Currency string for small per-unit amounts (avoids $0.00 when rate is e.g. $0.002541). */
        formatPerUnitCurrency(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value)) || Number(value) <= 0) {
                return '—';
            }

            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 6,
            }).format(Number(value));
        },

        applyCustomAmountPrice(payload) {
            const productId = payload?.productId ?? payload?.product_id ?? null;
            const price = Number(payload?.price ?? payload?.final_price);
            const requestedRaw = payload?.requestedAmount ?? payload?.requested_amount ?? null;

            if (! productId || Number.isNaN(price)) {
                return;
            }

            const item = this.items.find((entry) => entry.id === productId);

            if (! item) {
                return;
            }

            item.price = price;
            const req = Number(requestedRaw);
            if (! Number.isNaN(req) && req > 0) {
                item.custom_unit_rate = Math.round((price / req) * 1e8) / 1e8;
            }

            delete this.customAmountErrors[productId];
            this.persist();
        },
        setCustomAmountError(payload) {
            const productId = payload?.productId ?? payload?.product_id ?? null;
            const message = payload?.message ?? null;

            if (! productId) {
                return;
            }

            if (! message) {
                delete this.customAmountErrors[productId];
                return;
            }

            this.customAmountErrors[productId] = message;
        },
        updateRequestedAmount(id, value) {
            const item = this.items.find((entry) => entry.id === id);

            if (! item || item.amount_mode !== 'custom') {
                return false;
            }

            item.quantity = 1;

            if (item.requested_amount_input === undefined || item.requested_amount_input === null) {
                item.requested_amount_input = this.formatGroupedIntegerForAmount(item.requested_amount ?? 1);
            }

            const parsedValue = parseInt(String(value ?? '').replace(/\D/g, ''), 10);
            if (Number.isNaN(parsedValue) || parsedValue <= 0) {
                this.customAmountErrors[id] = 'Amount is required.';
                this.persist();
                return false;
            }

            const min = item.custom_amount_min;
            const max = item.custom_amount_max;
            const step = Math.max(1, parseInt(item.custom_amount_step ?? 1, 10));

            if (min !== null && min !== undefined && parsedValue < min) {
                this.customAmountErrors[id] = `Amount must be at least ${min}.`;
                this.persist();
                return false;
            }

            if (max !== null && max !== undefined && parsedValue > max) {
                this.customAmountErrors[id] = `Amount may not be greater than ${max}.`;
                this.persist();
                return false;
            }

            if (step > 1 && parsedValue % step !== 0) {
                this.customAmountErrors[id] = `Amount must be in increments of ${step}.`;
                this.persist();
                return false;
            }

            item.requested_amount = parsedValue;
            item.requested_amount_input = this.formatGroupedIntegerForAmount(parsedValue);
            delete this.customAmountErrors[id];
            this.persist();

            return true;
        },
        attemptCheckout($wire) {
            if (this.count === 0) {
                return;
            }

            if (this.hasCustomAmountErrors) {
                return;
            }

            if (this.hasMissingRequirements) {
                this.showCartRequirementErrors = true;

                return;
            }

            $wire.checkout(this.checkoutItems);
        },
        format(value) {
            return this.formatter.format(Number(value ?? 0));
        },
        get count() {
            return this.items.reduce((total, item) => total + (item.amount_mode === 'custom' ? 1 : item.quantity), 0);
        },
        get subtotal() {
            return this.items.reduce((total, item) => total + this.lineTotalForItem(item), 0);
        },
        get loyaltyDiscount() {
            return this.items.reduce((total, item) => total + (Number(item.discount_amount ?? 0) * item.quantity), 0);
        },
        get loyaltyTierName() {
            const withTier = this.items.find((item) => item.tier_name);
            return withTier ? withTier.tier_name : null;
        },
        get loyaltyTierLabel() {
            const name = this.loyaltyTierName;
            if (!name) return '';
            const labels = window.Laravel?.loyaltyTierLabels ?? {};
            return labels[name] ?? (name.charAt(0).toUpperCase() + name.slice(1));
        },
        get hasMissingRequirements() {
            return this.items.some((item) => {
                const schema = Array.isArray(item.requirements_schema) ? item.requirements_schema : [];

                if (schema.length === 0) {
                    return false;
                }

                return schema.some((requirement) => this.isRequirementMissing(item, requirement) || this.isRequirementInvalid(item, requirement));
            });
        },
        get hasCustomAmountErrors() {
            if (Object.keys(this.customAmountErrors).length > 0) {
                return true;
            }

            return this.items.some((item) => {
                if (item.amount_mode !== 'custom') {
                    return false;
                }

                const n = this.parseAmountDigitsFromInput(item.requested_amount_input ?? '');

                if (n === null || n < 1) {
                    return true;
                }

                return ! this.customAmountPreviewValid(item, n);
            });
        },
        get checkoutItems() {
            return this.items.map((item) => {
                let requestedAmount = item.amount_mode === 'custom' ? item.requested_amount : null;

                if (item.amount_mode === 'custom') {
                    const fromInput = this.parseAmountDigitsFromInput(item.requested_amount_input ?? '');
                    if (fromInput !== null && this.customAmountPreviewValid(item, fromInput)) {
                        requestedAmount = fromInput;
                    }
                }

                return {
                    product_id: item.id,
                    package_id: item.package_id ?? null,
                    quantity: item.amount_mode === 'custom' ? 1 : item.quantity,
                    requested_amount: requestedAmount,
                    requirements: item.requirements ?? {},
                };
            });
        },
    });

    Alpine.store('cart').init();
});

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
import './bug-reporter';

if (window.Laravel?.isAdmin === true) {
    import('./firebase-push');
}
