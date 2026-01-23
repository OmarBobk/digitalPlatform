document.addEventListener('alpine:init', () => {
    const { Alpine } = window;

    if (! Alpine) {
        return;
    }

    Alpine.store('cart', {
        storageKey: 'karman.cart.v1',
        hydrated: false,
        items: [],
        formatter: new Intl.NumberFormat('tr-TR', {
            style: 'currency',
            currency: 'TRY',
            maximumFractionDigits: 0,
        }),
        init() {
            if (this.hydrated) {
                return;
            }

            this.items = this.read();
            this.hydrated = true;
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
        add(product) {
            if (! product?.id) {
                return;
            }

            const existing = this.items.find((item) => item.id === product.id);

            if (existing) {
                existing.quantity += 1;
            } else {
                this.items.push({
                    id: product.id,
                    name: product.name,
                    price: Number(product.price ?? 0),
                    image: product.image,
                    href: product.href,
                    quantity: 1,
                });
            }

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
            this.persist();
        },
        clear() {
            this.items = [];
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
    });

    Alpine.store('cart').init();
});
