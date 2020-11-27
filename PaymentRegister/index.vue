<template>
    <div>
        <div class="block block-rounded">
            <div class="block-header block-header-default">
                <h2 class="block-title h2">
                    Реестр платежей
                </h2>
                <div class="block-options"></div>
            </div>
            <div class="block-header row justify-content-start ">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Фильтр по дате</label>
                        <flat-pickr v-model="filter.date"
                                    class="form-control mr-5"
                                    @input="updateTable"
                                    :config="datepickerConfig"></flat-pickr>
                    </div>
                </div>
                <div class="col-md-3"
                     v-if="isOwner">
                    <InputField labelText="Фильтр по франчайзи"
                                name="franchise"
                                :options="franchiseList"
                                :isMultiselect="true"
                                @input="updateTable"
                                v-model="filter.franchiseId"
                                :value="filter.franchiseId"
                                inputClass="col-md-12"/>
                </div>
                <div class="col-md-3"
                     v-else>
                    <InputField labelText="Фильтр по школе"
                                name="school"
                                :options="schoolsList"
                                :isMultiselect="true"
                                @input="updateTable"
                                v-model="filter.schoolId"
                                :value="filter.schoolId"
                                inputClass="col-md-12"/>
                </div>
                <div class="col-md-3"
                >
                    <InputField labelText="Фильтр по группе"
                                name="group"
                                :options="groupsList"
                                :isMultiselect="true"
                                @input="updateTable"
                                v-model="filter.groupId"
                                :value="filter.groupId"
                                inputClass="col-md-12"/>
                </div>
                <div class="col-md-3"
                >
                    <InputField labelText="Фильтр по курсу"
                                name="course"
                                :options="coursesList"
                                :isMultiselect="true"
                                @input="updateTable"
                                v-model="filter.courseId"
                                :value="filter.courseId"
                                inputClass="col-md-12"/>
                </div>
            </div>
            <div class="block-content c-loader__box">
                <div class="row text-center">
                    <div class="col-md-10" style="margin: 0 auto;">

                        <div class="row">
                            <div class="col-md-3 flex-wrap">
                                <p>Сумма за весь период: <b>{{ summa_total }}</b></p>
                            </div>
                            <div class="col-md-3 flex-wrap">
                                <p>Количество операций: <b>{{ operation_count }}</b></p>
                            </div>
                            <div class="col-md-3 flex-wrap">
                                <p>Средний чек: <b>{{ avg_check }}</b></p>
                            </div>
                            <div class="col-md-3 flex-wrap">
                                <p>Сумма возвратов: <b>{{ summa_return }}</b></p>
                            </div>
                        </div>
                        <Chart :datasets="datasets"
                               :grid="grid"
                               :labels="labels"/>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table id="data-table"
                       class="table table-bordered table-striped table-vcenter dataTable no-footer">
                    <thead>
                    <tr>
                        <th>№</th>
                        <th>Ученик</th>
                        <th>Номер телефона</th>
                        <th>Школа</th>
                        <th v-if="isOwner">Франчайзи</th>
                        <th v-if="!isOwner">Группа</th>
                        <th>Курс</th>
                        <th>Дата оплаты</th>
                        <th>Способ оплаты</th>
                        <th>Сумма</th>
                        <th>Оплачено</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>


</template>
<script>
import InputField from "@/js/components/InputField";
import dataTableConfig from "@/js/helpers/dataTables";
import {DATEPICKER_CONFIG} from "@/js/configs/datepickerConfig";
import flatPickr from "vue-flatpickr-component";
import "flatpickr/dist/flatpickr.css";
import moment from "moment";
import PaymentRegister from "@/js/api/PaymentRegister";
import Chart from "@/js/components/Chart";

export default {
    name: "PaymentRegister",
    components: {
        flatPickr,
        InputField,
        Chart,
    },
    data() {
        return {
            filter: {
                franchiseId: null,
                schoolId: null,
                date: null,
                groupId: null,
                courseId: null
            },
            table: null,
            isOwner: app_data.is_owner,
            datepickerConfig: {
                ...DATEPICKER_CONFIG,
                mode: "range"
            },
            summa_total: 0,
            operation_count: 0,
            avg_check: 0,
            summa_return: 0,
            datasets: [
                {
                    data: [0, 0],
                    smooth: true,
                    showPoints: true,
                    className: "curve3",
                    fill: true,
                }
            ],
            grid: {
                verticalLines: true,
                verticalLinesNumber: 6,
                horizontalLines: true,
                horizontalLinesNumber: 5
            },
            labels: {
                xLabels: [0, 0],
                yLabels: 5
            },
            isLoading: true,
            tooltipData: null,
            popper: null,
            popperIsActive: false
        }
    },
    mounted() {
        this.init()
        this.tableInit()
    },
    methods: {
        init() {
            const date = new Date()
            const firstDate = date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate()
            const secondDate = date.getFullYear() + '-' + (date.getMonth()) + '-' + date.getDate()
            this.filter.date = `${secondDate} — ${firstDate}`
        },
        updateTable() {
            if (this.filter.date.length > 11 || this.filter.date.length === 0) {
                this.table.draw()
                this.getCounts()
                this.hideEditBtn = true;
            }
        },
        updateChart: async function (param) {
            let {data} = await PaymentRegister.getCounts(param);
            const parseData = data.data

            if (typeof parseData === 'undefined' || parseData.length === 0) {
                this.datasets[0].data = [0, 0]
                this.labels.xLabels = [0, 0]
                return false
            }

            if (param.date_to === undefined && param.date_from === undefined) {
                param.date_to = param.date_to ? param.date_to : parseData[0].pay_at
                param.date_from = param.date_from ? param.date_from : parseData[parseData.length - 1].pay_at
                return false
            }

            this.summa_total = 0
            this.avg_check = 0
            this.operation_count = 0
            this.summa_return = 0

            const tempArr = []
            const tempArr2 = []

            data = await PaymentRegister.getDateRange(param)
            const dateRange = data.data.data

            for (let i = 0; i < parseData.length; i++) {
                let payAmount = parseInt(parseData[i].pay_amount) >= 0 ? parseInt(parseData[i].pay_amount) : 0
                let payAmountReturn = parseInt(parseData[i].pay_amount) < 0 ? parseInt(parseData[i].pay_amount) : 0
                this.summa_return += payAmountReturn
                this.summa_total += payAmount
                let price = tempArr[parseData[i].date_pay] ?? 0
                tempArr[parseData[i].date_pay] = parseInt(price) + payAmount
            }
            this.summa_return = Math.abs(this.summa_return)
            let i
            for (i = 0; i < dateRange.length; i++) {
                if (!tempArr[dateRange[i]]) {
                    tempArr2[dateRange[i]] = 0
                } else {
                    tempArr2[dateRange[i]] = tempArr[dateRange[i]]
                }
            }

            const tempData = []
            const labelsTemp = []

            let j = 0
            for (let key in tempArr2) {
                tempData.push(tempArr2[key])
                labelsTemp.push(key)
            }

            this.datasets[0].data = tempData //.reverse();
            this.labels.xLabels = labelsTemp
            this.operation_count = parseData.length
            if (this.summa_total || this.operation_count) {
                this.avg_check = Math.round(this.summa_total / this.operation_count)
            }
        },
        tableInit() {
            const _this = this;
            this.table = $("#data-table").DataTable(
                dataTableConfig({
                    ajax: {
                        url: "/api/admin/analytics/payment-register",
                        data(data) {
                            if (_this.isOwner) {
                                data.franchise_id = _this.filter.franchiseId;
                            } else {
                                data.school_id = _this.filter.schoolId;
                            }

                            if (_this.filter.date) {
                                const dateArray = _this.filter.date.split("—");
                                if (Array.isArray(dateArray)) {
                                    data.date_from = dateArray[0] ? dateArray[0].trim() : "";
                                    data.date_to = dateArray[1] ? dateArray[1].trim() : "";
                                }
                            }
                            data.group_id = _this.filter.groupId;
                            data.course_id = _this.filter.courseId;
                        }
                    },
                    columns: [
                        {
                            data: "name",
                            name: "users.name",
                            searchable: true,
                            orderable: false,
                            render(data, type, row) {
                                return `<a href="/admin/franchise-leads/${row.user_id}/profile">${data}</a>`;
                            },
                        },
                        {
                            data: "username",
                            name: "users.username",
                            searchable: true,
                            orderable: false
                        },
                        {
                            data: "school_title",
                            searchable: false,
                            orderable: false
                        },
                        {
                            data: _this.isOwner ? "admins_name" : "group_title",
                            searchable: false,
                            orderable: false,
                            render(data, type, row) {
                                if (_this.isOwner) {
                                    return data;
                                }

                                return data ? data : row.group_old ? row.group_old : null
                            }
                        },
                        {
                            data: "service_name",
                            searchable: false,
                            orderable: false
                        },
                        {
                            data: "pay_at",
                            searchable: false,
                            orderable: false,
                            render(data) {
                                return moment(data, "YYYY-MM-DD HH:mm:ss").format(
                                    "DD.MM.YYYY HH:mm:ss"
                                );
                            }
                        },
                        {
                            data: "payment_type",
                            searchable: false,
                            orderable: false,
                            render(data) {
                                return `<span class="badge badge-pill ${
                                    data == "Наличные" ? "badge-success" : "badge-primary"
                                }"><i class="fa  ${
                                    data == "Наличные" ? "fa-rouble" : "fa-credit-card-alt"
                                } mr-5"></i>${data}</span>`;
                            }
                        },
                        {
                            data: "pay_amount",
                            searchable: false,
                            orderable: false
                        },
                        {
                            data: "response_amount",
                            searchable: false,
                            orderable: false
                        }
                    ]
                })
            );
        },
        getCounts: async function () {
            const _this = this;
            try {
                let param = {};

                if (_this.isOwner) {
                    param.franchise_id = _this.filter.franchiseId;
                } else {
                    param.school_id = _this.filter.schoolId;
                }

                if (_this.filter.date) {
                    const dateArray = _this.filter.date.split("—");
                    if (Array.isArray(dateArray)) {
                        param.date_from = dateArray[0] ? dateArray[0].trim() : "";
                        param.date_to = dateArray[1] ? dateArray[1].trim() : "";
                    }
                }

                param.group_id = _this.filter.groupId;
                param.course_id = _this.filter.courseId;
                this.updateChart(param)
            } catch ({response}) {

            }

        },
    },
    computed: {
        franchiseList() {
            const result = {}
            app_data.franchises.forEach(el => (result[el.franchise_id] = el.name))
            return result
        },
        schoolsList() {
            const result = {}
            app_data.schools.forEach(el => (result[el.id] = el.title))
            return result
        },
        groupsList() {
            const result = {}
            app_data.groups.forEach(el => {
                if (el.title) (result[el.id] = el.title)
            })
            return result
        },
        coursesList() {
            const result = {};
            app_data.courses.forEach(el => (result[el.id] = el.title));
            return result;
        }
    }
};
</script>
<style lang="scss">
.flatpickr-wrapper {
    width: 100%;
}

.vtc {
    height: 250px;
    font-size: 12px;
    @media (min-width: 699px) {
        height: 320px;
    }
}

.active-line {
    stroke: rgba(0, 0, 0, 0.2);
}

.point {
    stroke-width: 2;
    transition: stroke-width 0.2s;
}

.point.is-active {
    stroke-width: 5;
}


.labels {
    line {
        stroke: rgba(#000, 0.5);
    }
}

.y-labels {
    .label {
        text {
            display: block;
        }

        line {
            opacity: 1;
        }
    }
}

.curve3 {
    .stroke {
        stroke: #7fdfd4;
        stroke-width: 2;
    }

    .point {
        fill: #7fdfd4;
        stroke: #7fdfd4;
    }

    .fill {
        fill: #7fdfd4;
        opacity: 0.5;
    }
}

</style>
