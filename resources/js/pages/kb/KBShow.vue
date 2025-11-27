<template>
  <div>
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="/">Home</a>
      </li>
      <li class="breadcrumb-item">
        <a href="/kb">KB</a>
      </li>
      <li class="breadcrumb-item active">
        {{ kb.title }}
      </li>
    </ol>
    <div
      class="container-fluid"
    >
      <div class="card">
        <div class="card-body">
          <h1 v-if="kb.title !== 'Home'">
            {{ kb.title }}
          </h1>
          <hr v-if="kb.title !== 'Home'">
          <table
            v-for="(alert, index) in alerts"
            :key="index"
            class="alert-table"
          >
            <tr>
              <td class="alert-table-image">
                <img :src="`/img/alert-${alert.icon == 4 ? '4.gif' : `${alert.icon}png`}`">
              </td>
              <td class="alert alert-tpv">
                {{ alert.message }}
              </td>
            </tr>
          </table>
          <div
            class=""
            v-html="kb.content"
          />
        </div>
        <div class="card-footer">
          <div class="small-text pull-left">
            <div><a :href="`/kb/versions/${kb.kb_id}`">VS-{{ kb.version }}.0-{{ $moment(kb.updated_at).format('MM/YYYY') }}</a></div>
          </div>
          <div
            v-if="!isGuest"
            class="form-group pull-right mb-0"
          >
            <a
              class="btn btn-sm btn-primary"
              :href="`/kb/edit/${kb.kb_id}`"
            ><i class="fa fa-pencil" /> Edit</a>
            <a
              class="btn btn-sm btn-danger"
              :href="`/kb/del/${kb.kb_id}`"
              @click="deleteKB($event)"
            ><i class="fa fa-trash" /> Delete</a>
            <a
              class="btn btn-sm btn-success"
              :href="`/kb/${kb.kb_id}?raw=1`"
            ><i class="fa fa-eye" /> Plain View</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
export default {
    props: {
        isGuest: { type: Boolean, default: false },
        kb: {type: Object, required: true},
    },
    data() {
        return {
      
            alerts: [],
            dataIsLoaded: true,
        };
    },
    
    updated() {
        if (!this.isGuest) {
            if (!$('.flashcard').length == 0) {
                $('.flashcard')
                    .find('h4')
                    .html('{{$kb->title}} (Flashcard)');
                $('.flashcard').removeClass('modal');
            }
            $('.knowledge-base-content table').each((index, item) => {
                const $item = $(item);
                $item.addClass('col-md-6');
                $item.addClass('col-lg-4');
                $item.addClass('col-sm-10');
                $item.addClass('table');
            });

            $('table').each((index, table) => {
                if (
                    $(table).find('th').length > 0
          && $(table)
              .find('th')
              .html() == ''
                ) {
                    $(table)
                        .find('thead')
                        .remove();
                }
                let doIt = false;
                $(table)
                    .find('tr')
                    .each((index, tr) => {
                        if (index == 0) {
                            if ($($(tr).find('td')[0]).length == 0) { return; }

                            if (
                                $($(tr).find('td')[0])
                                    .html()
                                    .includes('State')
                            ) {
                                doIt = true;
                            }
                        }
                        if (doIt) {
                            const td = $($(tr).find('td'));
                            $(td[0]).addClass('alert-info');
                            $(td[2]).addClass('alert-info');
                        }
                    });
            });
        }
    },
    methods: {
        deleteKB(e) {
            if (!this.isGuest) {
                e.preventDefault();
                if (
                    !confirm(
                        'Are you sure you want to delete this page, it cannot be undone!',
                    )
                ) {
                    return false;
                }
                window.location.href = `/kb/del/${kb.id}`;
            }
        },
    },
};
</script>
<style>
.knowledge-base-content table,
.knowledge-base-content td {
  border: 2px solid #333;
}
</style>
