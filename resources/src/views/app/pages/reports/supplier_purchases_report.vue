<template>
  <div class="main-content">
    <breadcumb :page="$t('Supplier_Purchases_Report') || 'Supplier Purchases Report'" :folder="$t('Purchases') || 'Purchases'"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card class="wrapper print-table-only" v-if="!isLoading">
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="reports"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
          placeholder: $t('Search_this_table'),
          enabled: true,
        }"
        :pagination-options="{
          enabled: true,
          mode: 'records',
          nextLabel: 'next',
          prevLabel: 'prev',
        }"
        styleClass="tableOne table-hover vgt-table mt-3"
      >
        <!-- Warehouse Filter -->
        <div slot="table-actions" class="mt-2 mb-3 quantity_alert_warehouse">
          <b-form-group :label="$t('warehouse')">
            <v-select
              @input="Selected_Warehouse"
              v-model="warehouse_id"
              :reduce="label => label.value"
              :placeholder="$t('Choose_Warehouse')"
              :options="warehouses.map(w => ({label: w.name, value: w.id}))"
            />
          </b-form-group>
        </div>

        <!-- Export Actions -->
        <div slot="table-actions" class="mt-2 mb-3">
          <b-button @click="printTableOnly()" size="sm" variant="outline-secondary ripple m-1">
            <lucide-icon name="printer" /> {{ $t("print") }}
          </b-button>
          <b-button @click="exportPDF()" size="sm" variant="outline-success ripple m-1">
            <lucide-icon name="copy" /> PDF
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <!-- Supplier Info -->
          <span v-if="props.column.field === 'supplier_name'">
            <div class="font-weight-bold text-primary">{{ props.row.supplier_name }}</div>
            <small class="text-muted" v-if="props.row.supplier_phone">Phone: {{ props.row.supplier_phone }}</small>
          </span>

          <!-- Total Purchases -->
          <span v-else-if="props.column.field === 'total_purchases_amount'">
            <b-badge variant="primary" class="p-1">
              {{ formatCurrency(props.row.total_purchases_amount) }}
            </b-badge>
            <small class="d-block text-muted">({{ props.row.total_purchases_count }} Invoices)</small>
          </span>

          <!-- Total Quantity -->
          <span v-else-if="props.column.field === 'total_quantity'">
            <b-badge variant="info" class="p-1">
              {{ props.row.total_quantity }} Qty
            </b-badge>
          </span>

          <!-- Action Details Button (Navigates to new details page) -->
          <span v-else-if="props.column.field === 'actions'">
            <router-link
              :to="{ name: 'supplier_purchases_detail', params: { id: props.row.id } }"
              class="btn btn-sm btn-primary ripple"
            >
              <lucide-icon name="eye" class="mr-1" /> Details
            </router-link>
          </span>
        </template>
      </vue-good-table>
    </b-card>
  </div>
</template>

<script>
import jsPDF from "jspdf";
import "jspdf-autotable";

export default {
  metaInfo: {
    title: "Supplier Purchases Report"
  },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10,
        search: ""
      },
      limit: "10",
      totalRows: 0,
      reports: [],
      warehouses: [],
      warehouse_id: "",
    };
  },
  computed: {
    columns() {
      return [
        {
          label: this.$t("Supplier") || "Supplier",
          field: "supplier_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Total_Purchases") || "Total Purchases",
          field: "total_purchases_amount",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Total_Quantity") || "Total Quantity",
          field: "total_quantity",
          tdClass: "text-center",
          thClass: "text-center"
        },
        {
          label: this.$t("Action") || "Action",
          field: "actions",
          html: true,
          tdClass: "text-center",
          thClass: "text-center",
          sortable: false
        }
      ];
    }
  },
  methods: {
    formatCurrency(val) {
      const num = Number(val) || 0;
      return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    onPageChange(params) {
      this.updateParams({ page: params.currentPage });
      this.getReport(params.currentPage);
    },

    onPerPageChange(params) {
      this.updateParams({ perPage: params.currentPerPage });
      this.getReport(1);
    },

    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field
        }
      });
      this.getReport(this.serverParams.page);
    },

    onSearch(params) {
      this.updateParams({ search: params.searchTerm });
      this.getReport(1);
    },

    Selected_Warehouse(val) {
      this.warehouse_id = val || "";
      this.getReport(1);
    },

    getReport(page) {
      this.isLoading = true;
      axios
        .get(
          "report/supplier_purchases_report?page=" +
            page +
            "&limit=" +
            this.serverParams.perPage +
            "&search=" +
            this.serverParams.search +
            "&warehouse_id=" +
            this.warehouse_id +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type
        )
        .then(response => {
          this.reports = response.data.reports || [];
          this.totalRows = response.data.totalRows || 0;
          this.warehouses = response.data.warehouses || [];
          this.isLoading = false;
        })
        .catch(() => {
          this.isLoading = false;
        });
    },

    printTableOnly() {
      window.print();
    },

    exportPDF() {
      const doc = new jsPDF("p", "pt", "a4");
      doc.setFontSize(16);
      doc.text("Supplier Purchases Report", 40, 40);

      const tableData = this.reports.map(item => [
        item.supplier_name + (item.supplier_phone ? ' (' + item.supplier_phone + ')' : ''),
        this.formatCurrency(item.total_purchases_amount) + ' (' + item.total_purchases_count + ' Invoices)',
        item.total_quantity + ' Qty'
      ]);

      doc.autoTable({
        head: [["Supplier", "Total Purchases", "Total Quantity"]],
        body: tableData,
        startY: 60,
      });

      doc.save("Supplier_Purchases_Report.pdf");
    }
  },
  created() {
    this.getReport(1);
  }
};
</script>
