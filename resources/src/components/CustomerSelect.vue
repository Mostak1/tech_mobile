<template>
  <v-select
    ref="vSelect"
    class="customer-search-select"
    :options="options"
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
    <!-- No options / prompt slot -->
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

    <!-- Custom option template with Name and Phone in brackets -->
    <template slot="option" slot-scope="option">
      <div class="d-flex align-items-center justify-content-between py-1 w-100" style="gap: 8px;">
        <span class="customer-opt-name font-weight-bold text-dark">{{ option.name }}</span>
        <span v-if="option.phone" class="customer-opt-phone text-primary small font-weight-bold" style="white-space: nowrap;">
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
      options: [],
      loading: false,
      searchQuery: "",
      debounceTimer: null,
      activeSearchLoading: null,
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
  },
  watch: {
    value: {
      immediate: true,
      handler(newVal) {
        this.internalValue = newVal;
        if (newVal !== null && newVal !== "" && newVal !== undefined) {
          this.ensureSelectedOptionExists(newVal);
        }
      },
    },
    initialCustomer: {
      immediate: true,
      handler(cust) {
        if (cust && cust.id) {
          this.pushCustomerOption(cust);
        }
      },
    },
    initialOptions: {
      immediate: true,
      handler(list) {
        if (Array.isArray(list) && list.length > 0) {
          list.forEach(item => this.pushCustomerOption(item));
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

    pushCustomerOption(cust) {
      const formatted = this.formatCustomer(cust);
      if (!formatted || !formatted.id) return;
      const idx = this.options.findIndex(o => String(o.value) === String(formatted.value));
      if (idx === -1) {
        this.options.unshift(formatted);
      } else {
        this.$set(this.options, idx, formatted);
      }
    },

    ensureSelectedOptionExists(id) {
      if (!id) return;
      const exists = this.options.some(o => String(o.value) === String(id));
      if (!exists) {
        if (this.initialCustomer && String(this.initialCustomer.id) === String(id)) {
          this.pushCustomerOption(this.initialCustomer);
          return;
        }
        this.fetchSelectedCustomer(id);
      }
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
            this.pushCustomerOption(item);
            if (String(this.internalValue) === String(item.value)) {
              this.$emit("selected", item);
            }
          }
        })
        .catch(err => {
          // Silent fallback
        });
    },

    onSearch(search, loading) {
      this.searchQuery = search || "";
      if (!search || search.trim().length < this.minSearchLength) {
        loading(false);
        return;
      }

      loading(true);
      this.activeSearchLoading = loading;

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

          // Preserve selected customer so vue-select does not drop the label
          if (this.internalValue !== null && this.internalValue !== "") {
            const selectedOpt = this.options.find(
              o => String(o.value) === String(this.internalValue)
            );
            if (selectedOpt && !results.some(r => String(r.value) === String(this.internalValue))) {
              results.unshift(selectedOpt);
            }
          }

          this.options = results;
          if (typeof loading === "function") loading(false);
        })
        .catch(err => {
          console.error("Error searching customers:", err);
          if (typeof loading === "function") loading(false);
        });
    },

    onInput(val) {
      this.internalValue = val;
      const selectedCustomer = this.options.find(o => String(o.value) === String(val)) || null;

      this.$emit("input", val);
      this.$emit("change", val);
      this.$emit("selected", selectedCustomer);
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
      this.pushCustomerOption(formatted);
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

<style scoped>
.customer-search-select {
  width: 100%;
}
.customer-opt-name {
  font-size: 0.9rem;
}
.customer-opt-phone {
  font-size: 0.82rem;
}
</style>

