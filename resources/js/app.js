document.addEventListener('alpine:init', () => {
    const { Alpine } = window;

    if (! Alpine) {
        return;
    }

    Alpine.store('cart', {
        storageKey: 'karman.cart.v1',
        hydrated: false,
        items: [],
        validationMessages: {},
        serverRequirementErrors: {},
        formatter: new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
            maximumFractionDigits: 2,
        }),
        init() {
            if (this.hydrated) {
                return;
            }

            this.items = this.read();
            this.hydrated = true;
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
                return JSON.parse(localStorage.getItem(this.storageKey) ?? '[]');
            } catch (error) {
                return [];
            }
        },
        persist() {
            localStorage.setItem(this.storageKey, JSON.stringify(this.items));
        },
        add(product, quantity = 1) {
            if (! product?.id) {
                return;
            }

            const normalizedQuantity = Math.max(1, parseInt(quantity ?? 1, 10));
            const existing = this.items.find((item) => item.id === product.id);

            if (existing) {
                existing.quantity += normalizedQuantity;
            } else {
                const requirementsSchema = Array.isArray(product.requirements_schema)
                    ? product.requirements_schema
                    : [];
                const requirements = {};

                requirementsSchema.forEach((requirement) => {
                    if (requirement?.key) {
                        requirements[requirement.key] = '';
                    }
                });

                this.items.push({
                    id: product.id,
                    name: product.name,
                    price: Number(product.price ?? 0),
                    discount_amount: Number(product.discount_amount ?? 0),
                    tier_name: product.tier_name ?? null,
                    image: product.image,
                    href: product.href,
                    quantity: normalizedQuantity,
                    package_id: product.package_id ?? null,
                    requirements,
                    requirements_schema: requirementsSchema,
                });
            }

            this.persist();

            if (product?.name) {
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

            item.quantity += 1;
            this.persist();
        },
        decrement(id) {
            const item = this.items.find((entry) => entry.id === id);

            if (! item) {
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
            this.persist();
        },
        clear() {
            this.items = [];
            this.serverRequirementErrors = {};
            this.persist();
        },
        format(value) {
            return this.formatter.format(Number(value ?? 0));
        },
        get count() {
            return this.items.reduce((total, item) => total + item.quantity, 0);
        },
        get subtotal() {
            return this.items.reduce((total, item) => total + (item.price * item.quantity), 0);
        },
        get loyaltyDiscount() {
            return this.items.reduce((total, item) => total + (Number(item.discount_amount ?? 0) * item.quantity), 0);
        },
        get loyaltyTierName() {
            const withTier = this.items.find((item) => item.tier_name);
            return withTier ? withTier.tier_name : null;
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
    });

    Alpine.store('cart').init();
});

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';
