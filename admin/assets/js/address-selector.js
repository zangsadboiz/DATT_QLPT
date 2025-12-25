/**
 * Address Selector - Cascading Dropdowns
 * Miền → Tỉnh → Quận/Huyện
 * 
 * Usage:
 * const selector = new AddressSelector({
 *     regionSelect: '#region',
 *     provinceSelect: '#province', 
 *     districtSelect: '#district',
 *     apiBasePath: '/admin/api'
 * });
 */

class AddressSelector {
    constructor(options) {
        this.regionSelect = document.querySelector(options.regionSelect);
        this.provinceSelect = document.querySelector(options.provinceSelect);
        this.districtSelect = document.querySelector(options.districtSelect);
        this.apiBasePath = options.apiBasePath || '/admin/api';

        this.init();
    }

    init() {
        // Load regions on page load
        this.loadRegions();

        // Bind change events
        if (this.regionSelect) {
            this.regionSelect.addEventListener('change', () => {
                const regionId = this.regionSelect.value;
                this.loadProvinces(regionId);
                this.clearSelect(this.districtSelect);
            });
        }

        if (this.provinceSelect) {
            this.provinceSelect.addEventListener('change', () => {
                const provinceId = this.provinceSelect.value;
                this.loadDistricts(provinceId);
            });
        }
    }

    async loadRegions() {
        try {
            const response = await fetch(`${this.apiBasePath}/get_regions.php`);
            const data = await response.json();

            if (data.success) {
                this.populateSelect(this.regionSelect, data.data, 'region_id', 'region_name');
            }
        } catch (error) {
            console.error('Error loading regions:', error);
        }
    }

    async loadProvinces(regionId) {
        if (!regionId) {
            this.clearSelect(this.provinceSelect);
            return;
        }

        try {
            const response = await fetch(`${this.apiBasePath}/get_provinces_by_region.php?region_id=${regionId}`);
            const data = await response.json();

            if (data.success) {
                this.populateSelect(this.provinceSelect, data.data, 'province_id', 'province_name');
            }
        } catch (error) {
            console.error('Error loading provinces:', error);
        }
    }

    async loadDistricts(provinceId) {
        if (!provinceId) {
            this.clearSelect(this.districtSelect);
            return;
        }

        try {
            const response = await fetch(`${this.apiBasePath}/get_districts_by_province.php?province_id=${provinceId}`);
            const data = await response.json();

            if (data.success) {
                this.populateSelect(this.districtSelect, data.data, 'district_id', 'district_name');
            }
        } catch (error) {
            console.error('Error loading districts:', error);
        }
    }

    populateSelect(selectElement, data, valueKey, textKey) {
        if (!selectElement) return;

        // Keep the first option (usually "-- Chọn --")
        const firstOption = selectElement.options[0];
        selectElement.innerHTML = '';
        if (firstOption) {
            selectElement.appendChild(firstOption);
        }

        // Add new options
        data.forEach(item => {
            const option = document.createElement('option');
            option.value = item[valueKey];
            option.textContent = item[textKey];
            selectElement.appendChild(option);
        });

        // Enable the select
        selectElement.disabled = false;
    }

    clearSelect(selectElement) {
        if (!selectElement) return;

        // Keep only the first option
        const firstOption = selectElement.options[0];
        selectElement.innerHTML = '';
        if (firstOption) {
            selectElement.appendChild(firstOption);
        }

        // Disable the select
        selectElement.disabled = true;
    }

    // Method to set selected values (useful for edit forms)
    setValues(regionId, provinceId, districtId) {
        if (regionId && this.regionSelect) {
            this.regionSelect.value = regionId;

            if (provinceId) {
                this.loadProvinces(regionId).then(() => {
                    this.provinceSelect.value = provinceId;

                    if (districtId) {
                        this.loadDistricts(provinceId).then(() => {
                            this.districtSelect.value = districtId;
                        });
                    }
                });
            }
        }
    }
}

// Make it available globally
window.AddressSelector = AddressSelector;
