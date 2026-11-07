<template>
  <v-select
    ref="vSelect"
    class="customer-search-select"
    :options="displayedOptions"
    :filterable="false"
    :loading="loading"
    :disabled="disabled"
    :placeholder="computedPlaceholder"
    :reduce="opt => opt.value"
    :clearable="clearable"
    v-model="internalValue"
    @search="onSearch"
    @input="onInput"
    @open="onOpen"
    @close="onClose"
    v-bind="$attrs"
  >
    <!-- No options / search prompt slot -->
    <template slot="no-options" slot-scope="{ search, searching, loading }">
      <div class="p-2 text-center text-muted small">
        <span v-if="loading">
          <span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>
          {{ $t('Searching') || 'Searching...' }}
        </span>
        <span v-else-if="!search || search.trim().length < minSearchLength">
          <lucide-icon name="search" class="mr-1" style="font-size: 13px; vertical-align: middle;" />
          {{ typeMinCharsText }}
        </span>
        <span v-else>
          {{ $t('No_Customer_Found') || 'No customer found' }}
        </span>
      </div>
    </template>

    <!-- Custom option template with Name and Phone in brackets (Light hover color matching Image 2) -->
    <template slot="option" slot-scope="option">
      <div class="d-flex align-items-center justify-content-between py-1 w-100" style="gap: 8px;">
        <span class="customer-opt-name">{{ option.name }}</span>
        <span v-if="option.phone" class="customer-opt-phone" style="white-space: nowrap;">
          ({{ option.phone }})
        </span>
      </div>
    </template>

    <!-- Selected option template -->
    <template slot="selected-option" slot-scope="option">
      <span class="customer-selected-label">
        {{ option.label || (option.phone ? `${option.name} (${option.phone})` : option.name) }}
      </span>
    </template>
  </v-select>
</template>

<script>
export default {
  name: "CustomerSelect",
  inheritAttrs: false,
  props: {
    value: {
      type: [Number, String],
      default: null,
    },
    placeholder: {
      type: String,
      default: null,
    },
    disabled: {
      type: Boolean,
      default: false,
    },
    clearable: {
      type: Boolean,
      default: true,
    },
    minSearchLength: {
      type: Number,
      default: 2,
    },
    debounceTime: {
      type: Number,
      default: 300,
    },
    searchLimit: {
      type: Number,
      default: 25,
    },
    initialCustomer: {
      type: Object,
      default: null,
    },
    initialOptions: {
      type: Array,
      default: () => [],
    },
  },
  data() {
    return {
      internalValue: this.value,
      searchResults: [],
      selectedCustomerObj: null,
      loading: false,
      searchQuery: "",
      debounceTimer: null,
    };
  },
  computed: {
    computedPlaceholder() {
      if (this.placeholder) return this.placeholder;
      return this.$t ? this.$t("Choose_Customer") : "Choose Customer";
    },
    typeMinCharsText() {
      const msg = this.$t ? this.$t("Type_to_search_customer") : null;
      if (msg) return msg;
      return `Type at least ${this.minSearchLength} characters (name or phone)...`;
    },
    // Only display customers when actively searching! Never preload or display default customers on open.
    displayedOptions() {
      if (this.searchQuery && this.searchQuery.trim().length >= this.minSearchLength) {
        return this.searchResults;
      }
      // If not searching, only retain the selected customer so the input displays its label
      if (this.selectedCustomerObj) {
        return [this.selectedCustomerObj];
      }
      return [];
    },
  },
  watch: {
    value: {
      immediate: true,
      handler(newVal) {
        this.internalValue = newVal;
        if (newVal !== null && newVal !== "" && newVal !== undefined) {
          this.ensureSelectedOptionExists(newVal);
        } else {
          this.selectedCustomerObj = null;
        }
      },
    },
    initialCustomer: {
      immediate: true,
      handler(cust) {
        if (cust && cust.id) {
          const formatted = this.formatCustomer(cust);
          this.selectedCustomerObj = formatted;
        }
      },
    },
  },
  methods: {
    formatCustomer(c) {
      if (!c) return null;
      const id = c.id !== undefined ? c.id : c.value;
      const name = (c.name || c.label || "").trim();
      const phone = (c.phone || "").trim();
      const label = phone ? `${name} (${phone})` : name;

      return {
        id,
        value: id,
        name,
        phone,
        label,
        code: c.code || "",
        email: c.email || "",
        city: c.city || "",
        tax_number: c.tax_number || "",
        points: typeof c.points === "number" ? c.points : parseFloat(c.points || 0),
        credit_limit: typeof c.credit_limit === "number" ? c.credit_limit : parseFloat(c.credit_limit || 0),
        is_royalty_eligible: c.is_royalty_eligible !== undefined ? Number(c.is_royalty_eligible) : 0,
      };
    },

    ensureSelectedOptionExists(id) {
      if (!id) return;
      if (this.selectedCustomerObj && String(this.selectedCustomerObj.value) === String(id)) {
        return;
      }
      if (this.initialCustomer && String(this.initialCustomer.id) === String(id)) {
        this.selectedCustomerObj = this.formatCustomer(this.initialCustomer);
        return;
      }
      this.fetchSelectedCustomer(id);
    },

    fetchSelectedCustomer(id) {
      if (!id) return;
      axios
        .get("search_clients", {
          params: { selected_id: id, limit: 1 },
        })
        .then(response => {
          const list = response.data || [];
          if (list.length > 0) {
            const item = this.formatCustomer(list[0]);
            this.selectedCustomerObj = item;
            if (String(this.internalValue) === String(item.value)) {
              this.$emit("selected", item);
            }
          }
        })
        .catch(() => {});
    },

    onSearch(search, loading) {
      this.searchQuery = search || "";
      if (!search || search.trim().length < this.minSearchLength) {
        this.searchResults = [];
        loading(false);
        return;
      }

      loading(true);

      if (this.debounceTimer) {
        clearTimeout(this.debounceTimer);
      }

      this.debounceTimer = setTimeout(() => {
        this.executeSearch(search.trim(), loading);
      }, this.debounceTime);
    },

    executeSearch(query, loading) {
      axios
        .get("search_clients", {
          params: {
            search: query,
            selected_id: this.internalValue,
            limit: this.searchLimit,
          },
        })
        .then(response => {
          const results = (response.data || []).map(this.formatCustomer);

          // Preserve currently selected customer so selection isn't lost
          if (this.selectedCustomerObj) {
            if (!results.some(r => String(r.value) === String(this.selectedCustomerObj.value))) {
              results.unshift(this.selectedCustomerObj);
            }
          }

          this.searchResults = results;
          if (typeof loading === "function") loading(false);
        })
        .catch(err => {
          console.error("Error searching customers:", err);
          if (typeof loading === "function") loading(false);
        });
    },

    onInput(val) {
      this.internalValue = val;
      const selected = (this.displayedOptions || []).find(o => String(o.value) === String(val))
        || (this.selectedCustomerObj && String(this.selectedCustomerObj.value) === String(val) ? this.selectedCustomerObj : null);

      this.selectedCustomerObj = selected;
      this.$emit("input", val);
      this.$emit("change", val);
      this.$emit("selected", selected);
    },

    onOpen() {
      this.$emit("open");
    },

    onClose() {
      this.$emit("close");
    },

    // Public API for parent components (e.g. Quick Add)
    addCustomer(cust) {
      if (!cust) return;
      const formatted = this.formatCustomer(cust);
      this.selectedCustomerObj = formatted;
      if (!this.searchResults.some(r => String(r.value) === String(formatted.value))) {
        this.searchResults.unshift(formatted);
      }
      this.internalValue = formatted.id;
      this.$emit("input", formatted.id);
      this.$emit("change", formatted.id);
      this.$emit("selected", formatted);
    },

    setCustomer(cust) {
      this.addCustomer(cust);
    },

    clear() {
      this.internalValue = null;
      this.selectedCustomerObj = null;
      this.searchResults = [];
      this.$emit("input", null);
      this.$emit("change", null);
      this.$emit("selected", null);
    },
  },
  beforeDestroy() {
    if (this.debounceTimer) {
      clearTimeout(this.debounceTimer);
    }
  },
};
</script>

<style>
/* Unscoped dropdown styling specifically for customer search dropdown */
/* Overrides global dark-purple hover with light lavender/purple tint matching Image 2 */
.v-select.customer-search-select .vs__dropdown-menu {
  padding: 4px 0 !important;
  border-radius: 6px !important;
  border: 1px solid #e2e8f0 !important;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08) !important;
  max-height: 280px !important;
}

.v-select.customer-search-select .vs__dropdown-menu .vs__dropdown-option {
  padding: 8px 14px !important;
  border-bottom: 1px solid #f6f5fb !important;
  background-color: transparent !important;
  color: #212529 !important;
  cursor: pointer !important;
  transition: background-color 0.12s ease !important;
}

.v-select.customer-search-select .vs__dropdown-menu .vs__dropdown-option:last-child {
  border-bottom: none !important;
}

/* Light purple highlight / hover state (Matches Image 2 exactly) */
.v-select.customer-search-select .vs__dropdown-menu .vs__dropdown-option--highlight,
.v-select.customer-search-select .vs__dropdown-menu .vs__dropdown-option--selected,
.v-select.customer-search-select .vs__dropdown-menu .vs__dropdown-option:hover {
  background: #f1edfa !important;
  background-color: #f1edfa !important;
  color: #212529 !important;
}

.v-select.customer-search-select .vs__dropdown-menu .vs__dropdown-option .customer-opt-name {
  color: #212529 !important;
  font-weight: 600;
  font-size: 0.9rem;
}

.v-select.customer-search-select .vs__dropdown-menu .vs__dropdown-option .customer-opt-phone {
  color: #6f42c1 !important;
  font-weight: 500;
  font-size: 0.85rem;
}

.v-select.customer-search-select .vs__dropdown-menu .vs__dropdown-option--highlight .customer-opt-name,
.v-select.customer-search-select .vs__dropdown-menu .vs__dropdown-option:hover .customer-opt-name {
  color: #212529 !important;
}

.v-select.customer-search-select .vs__dropdown-menu .vs__dropdown-option--highlight .customer-opt-phone,
.v-select.customer-search-select .vs__dropdown-menu .vs__dropdown-option:hover .customer-opt-phone {
  color: #6f42c1 !important;
}

.v-select.customer-search-select .vs__selected {
  color: #212529 !important;
  font-weight: 500;
}
</style>
