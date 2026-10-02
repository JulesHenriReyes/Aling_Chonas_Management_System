window.packageInclusions = function (catalog, initialRows) {
    return {
        catalog, rows: [], sequence: 0,
        init() {
            this.rows = Object.values(initialRows || {}).map(row => ({ ...row, uid: ++this.sequence }));
        },
        used(id, current) {
            return this.rows.some(row => row !== current && String(row.add_on_id) === String(id));
        },
        add() {
            const item = this.catalog.find(item => !this.used(item.id, null));
            if (item && this.rows.length < 50) {
                this.rows.push({ uid: ++this.sequence, add_on_id: item.id, quantity: 1 });
            }
        },
    };
};
