<script>
    /**
     * Reusable Premium Dropdown Logic
     */
    
    // Rebuild options for single select dropdowns
    function rebuildSingleDropdownOptions(hiddenInput, container, labelEl, options, selectedValue) {
        const normalizedOptions = Array.isArray(options) ? options : [];
        const selected = selectedValue !== null && selectedValue !== undefined ? String(selectedValue) : '';
        
        container.innerHTML = '';
        let foundSelected = false;

        normalizedOptions.forEach((option) => {
            const value = String(option.value ?? '');
            const label = String(option.label ?? value);
            if (value === '') return;

            const isActive = value === selected;
            if (isActive) {
                hiddenInput.value = value;
                labelEl.textContent = label;
                foundSelected = true;
            }

            const item = document.createElement('div');
            item.className = `loan-dropdown-item filter-single-option ${isActive ? 'active' : ''}`;
            item.dataset.value = value;
            item.dataset.label = label;
            item.innerHTML = `<span class="form-check-label">${label}</span>`;
            container.appendChild(item);
        });

        if (!foundSelected && normalizedOptions.length > 0) {
            const first = normalizedOptions[0];
            hiddenInput.value = first.value;
            labelEl.textContent = first.label;
            container.querySelector('.filter-single-option')?.classList.add('active');
        }
    }

    // Rebuild options for multi-select (checkbox) dropdowns
    function rebuildMultiDropdownOptions(container, toggleBtn, labelEl, options, preservedValues = [], emptyMsg = 'Tidak ada pilihan') {
        const normalizedPreserved = new Set((preservedValues || []).map((value) => String(value)));
        
        container.innerHTML = '';
        
        if (!options.length) {
            container.innerHTML = `<div class="p-3 text-center text-muted" style="font-size: 0.85rem;">${emptyMsg}</div>`;
            toggleBtn.disabled = true;
            updateMultiDropdownLabel(container, labelEl);
            return;
        }

        options.forEach((option) => {
            const isChecked = normalizedPreserved.has(String(option.value)) ? 'checked' : '';
            const item = document.createElement('div');
            item.className = 'loan-dropdown-item';
            item.innerHTML = `
                <div class="form-check">
                    <input class="form-check-input filter-unit-checkbox" type="checkbox" 
                        name="unit1[]" value="${option.value}" id="opt_${option.value}" ${isChecked}>
                    <label class="form-check-label" for="opt_${option.value}">
                        ${option.label}
                    </label>
                </div>
            `;
            container.appendChild(item);
        });

        toggleBtn.disabled = false;
        updateMultiDropdownLabel(container, labelEl);
    }

    // Update label for multi-select dropdowns
    function updateMultiDropdownLabel(container, labelEl, defaultText = 'Pilih Item') {
        const checked = Array.from(container.querySelectorAll('input[type="checkbox"]:checked'));
        if (checked.length === 0) {
            labelEl.textContent = defaultText;
        } else if (checked.length === 1) {
            const label = checked[0].closest('.form-check').querySelector('.form-check-label').textContent.trim();
            labelEl.textContent = label;
        } else {
            labelEl.textContent = `${checked.length} Item Terpilih`;
        }
    }

    // Initialize Dropdown Toggle & Click-outside logic
    function initDropdownHandlers(shell, toggle, menu) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            if (this.disabled) return;
            
            // Close other open menus
            document.querySelectorAll('.loan-dropdown-menu.show').forEach(m => {
                if (m !== menu) m.classList.remove('show');
            });
            
            menu.classList.toggle('show');
            this.setAttribute('aria-expanded', menu.classList.contains('show'));
        });

        document.addEventListener('click', function(e) {
            if (!shell.contains(e.target)) {
                menu.classList.remove('show');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
</script>
