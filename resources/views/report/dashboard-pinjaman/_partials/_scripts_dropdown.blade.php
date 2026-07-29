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
            const itemLabel = document.createElement('span');
            itemLabel.className = 'form-check-label';
            itemLabel.textContent = label;
            item.appendChild(itemLabel);
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
            const emptyState = document.createElement('div');
            emptyState.className = 'p-3 text-center text-muted';
            emptyState.style.fontSize = '0.85rem';
            emptyState.textContent = String(emptyMsg);
            container.appendChild(emptyState);
            toggleBtn.disabled = true;
            updateMultiDropdownLabel(container, labelEl);
            return;
        }

        options.forEach((option, index) => {
            const optionValue = String(option.value ?? '');
            const optionLabel = String(option.label ?? optionValue);
            const isChecked = normalizedPreserved.has(optionValue);
            const item = document.createElement('div');
            item.className = 'loan-dropdown-item';

            const formCheck = document.createElement('div');
            formCheck.className = 'form-check';

            const input = document.createElement('input');
            const inputId = `opt_${container.id || 'dropdown'}_${index}`;
            input.className = 'form-check-input filter-unit-checkbox';
            input.type = 'checkbox';
            input.name = 'unit1[]';
            input.value = optionValue;
            input.id = inputId;
            input.checked = isChecked;

            const optionLabelElement = document.createElement('label');
            optionLabelElement.className = 'form-check-label';
            optionLabelElement.htmlFor = inputId;
            optionLabelElement.textContent = optionLabel;

            formCheck.appendChild(input);
            formCheck.appendChild(optionLabelElement);
            item.appendChild(formCheck);
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
