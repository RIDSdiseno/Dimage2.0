import{Q as e,n as t,ot as n,st as r,vt as i}from"./service--Pk9P4Or.js";import{D as a,F as o,H as s,T as c,b as l,c as u,g as d,h as f,l as p,r as m,s as h,st as g,u as _,ut as v}from"./runtime-core.esm-bundler-qZAd3DqM.js";import{c as y,h as b,s as x,t as S}from"./button-D2gnqE_R.js";import{t as C}from"./AppLayout-D0s42lif.js";import{t as w}from"./message-whEqKxLv.js";var T=t.extend({name:`radiobutton`,style:`
    .p-radiobutton {
        position: relative;
        display: inline-flex;
        user-select: none;
        vertical-align: bottom;
        width: dt('radiobutton.width');
        height: dt('radiobutton.height');
    }

    .p-radiobutton-input {
        cursor: pointer;
        appearance: none;
        position: absolute;
        top: 0;
        inset-inline-start: 0;
        width: 100%;
        height: 100%;
        padding: 0;
        margin: 0;
        opacity: 0;
        z-index: 1;
        outline: 0 none;
        border: 1px solid transparent;
        border-radius: 50%;
    }

    .p-radiobutton-box {
        display: flex;
        justify-content: center;
        align-items: center;
        border-radius: 50%;
        border: 1px solid dt('radiobutton.border.color');
        background: dt('radiobutton.background');
        width: dt('radiobutton.width');
        height: dt('radiobutton.height');
        transition:
            background dt('radiobutton.transition.duration'),
            color dt('radiobutton.transition.duration'),
            border-color dt('radiobutton.transition.duration'),
            box-shadow dt('radiobutton.transition.duration'),
            outline-color dt('radiobutton.transition.duration');
        outline-color: transparent;
        box-shadow: dt('radiobutton.shadow');
    }

    .p-radiobutton-icon {
        transition-duration: dt('radiobutton.transition.duration');
        background: transparent;
        font-size: dt('radiobutton.icon.size');
        width: dt('radiobutton.icon.size');
        height: dt('radiobutton.icon.size');
        border-radius: 50%;
        backface-visibility: hidden;
        transform: translateZ(0) scale(0.1);
    }

    .p-radiobutton:not(.p-disabled):has(.p-radiobutton-input:hover) .p-radiobutton-box {
        border-color: dt('radiobutton.hover.border.color');
    }

    .p-radiobutton-checked .p-radiobutton-box {
        border-color: dt('radiobutton.checked.border.color');
        background: dt('radiobutton.checked.background');
    }

    .p-radiobutton-checked .p-radiobutton-box .p-radiobutton-icon {
        background: dt('radiobutton.icon.checked.color');
        transform: translateZ(0) scale(1, 1);
        visibility: visible;
    }

    .p-radiobutton-checked:not(.p-disabled):has(.p-radiobutton-input:hover) .p-radiobutton-box {
        border-color: dt('radiobutton.checked.hover.border.color');
        background: dt('radiobutton.checked.hover.background');
    }

    .p-radiobutton:not(.p-disabled):has(.p-radiobutton-input:hover).p-radiobutton-checked .p-radiobutton-box .p-radiobutton-icon {
        background: dt('radiobutton.icon.checked.hover.color');
    }

    .p-radiobutton:not(.p-disabled):has(.p-radiobutton-input:focus-visible) .p-radiobutton-box {
        border-color: dt('radiobutton.focus.border.color');
        box-shadow: dt('radiobutton.focus.ring.shadow');
        outline: dt('radiobutton.focus.ring.width') dt('radiobutton.focus.ring.style') dt('radiobutton.focus.ring.color');
        outline-offset: dt('radiobutton.focus.ring.offset');
    }

    .p-radiobutton-checked:not(.p-disabled):has(.p-radiobutton-input:focus-visible) .p-radiobutton-box {
        border-color: dt('radiobutton.checked.focus.border.color');
    }

    .p-radiobutton.p-invalid > .p-radiobutton-box {
        border-color: dt('radiobutton.invalid.border.color');
    }

    .p-radiobutton.p-variant-filled .p-radiobutton-box {
        background: dt('radiobutton.filled.background');
    }

    .p-radiobutton.p-variant-filled.p-radiobutton-checked .p-radiobutton-box {
        background: dt('radiobutton.checked.background');
    }

    .p-radiobutton.p-variant-filled:not(.p-disabled):has(.p-radiobutton-input:hover).p-radiobutton-checked .p-radiobutton-box {
        background: dt('radiobutton.checked.hover.background');
    }

    .p-radiobutton.p-disabled {
        opacity: 1;
    }

    .p-radiobutton.p-disabled .p-radiobutton-box {
        background: dt('radiobutton.disabled.background');
        border-color: dt('radiobutton.checked.disabled.border.color');
    }

    .p-radiobutton-checked.p-disabled .p-radiobutton-box .p-radiobutton-icon {
        background: dt('radiobutton.icon.disabled.color');
    }

    .p-radiobutton-sm,
    .p-radiobutton-sm .p-radiobutton-box {
        width: dt('radiobutton.sm.width');
        height: dt('radiobutton.sm.height');
    }

    .p-radiobutton-sm .p-radiobutton-icon {
        font-size: dt('radiobutton.icon.sm.size');
        width: dt('radiobutton.icon.sm.size');
        height: dt('radiobutton.icon.sm.size');
    }

    .p-radiobutton-lg,
    .p-radiobutton-lg .p-radiobutton-box {
        width: dt('radiobutton.lg.width');
        height: dt('radiobutton.lg.height');
    }

    .p-radiobutton-lg .p-radiobutton-icon {
        font-size: dt('radiobutton.icon.lg.size');
        width: dt('radiobutton.icon.lg.size');
        height: dt('radiobutton.icon.lg.size');
    }
`,classes:{root:function(e){var t=e.instance,n=e.props;return[`p-radiobutton p-component`,{"p-radiobutton-checked":t.checked,"p-disabled":n.disabled,"p-invalid":t.$pcRadioButtonGroup?t.$pcRadioButtonGroup.$invalid:t.$invalid,"p-variant-filled":t.$variant===`filled`,"p-radiobutton-sm p-inputfield-sm":n.size===`small`,"p-radiobutton-lg p-inputfield-lg":n.size===`large`}]},box:`p-radiobutton-box`,input:`p-radiobutton-input`,icon:`p-radiobutton-icon`}}),E={name:`BaseRadioButton`,extends:y,props:{value:null,binary:Boolean,readonly:{type:Boolean,default:!1},tabindex:{type:Number,default:null},inputId:{type:String,default:null},inputClass:{type:[String,Object],default:null},inputStyle:{type:Object,default:null},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null}},style:T,provide:function(){return{$pcRadioButton:this,$parentInstance:this}}};function D(e){"@babel/helpers - typeof";return D=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},D(e)}function O(e,t,n){return(t=k(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function k(e){var t=A(e,`string`);return D(t)==`symbol`?t:t+``}function A(e,t){if(D(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if(D(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}var j={name:`RadioButton`,extends:E,inheritAttrs:!1,emits:[`change`,`focus`,`blur`],inject:{$pcRadioButtonGroup:{default:void 0}},methods:{getPTOptions:function(e){return(e===`root`?this.ptmi:this.ptm)(e,{context:{checked:this.checked,disabled:this.disabled}})},onChange:function(e){if(!this.disabled&&!this.readonly){var t=this.binary?!this.checked:this.value;this.$pcRadioButtonGroup?this.$pcRadioButtonGroup.writeValue(t,e):this.writeValue(t,e),this.$emit(`change`,e)}},onFocus:function(e){this.$emit(`focus`,e)},onBlur:function(e){var t,n;this.$emit(`blur`,e),(t=(n=this.formField).onBlur)==null||t.call(n,e)}},computed:{groupName:function(){return this.$pcRadioButtonGroup?this.$pcRadioButtonGroup.groupName:this.$formName},checked:function(){var t=this.$pcRadioButtonGroup?this.$pcRadioButtonGroup.d_value:this.d_value;return t!=null&&(this.binary?!!t:e(t,this.value))},dataP:function(){return b(O({invalid:this.$invalid,checked:this.checked,disabled:this.disabled,filled:this.$variant===`filled`},this.size,this.size))}}},M=[`data-p-checked`,`data-p-disabled`,`data-p`],N=[`id`,`value`,`name`,`checked`,`tabindex`,`disabled`,`readonly`,`aria-labelledby`,`aria-label`,`aria-invalid`],P=[`data-p`],F=[`data-p`];function I(e,t,n,r,i,a){return c(),_(`div`,l({class:e.cx(`root`)},a.getPTOptions(`root`),{"data-p-checked":a.checked,"data-p-disabled":e.disabled,"data-p":a.dataP}),[h(`input`,l({id:e.inputId,type:`radio`,class:[e.cx(`input`),e.inputClass],style:e.inputStyle,value:e.value,name:a.groupName,checked:a.checked,tabindex:e.tabindex,disabled:e.disabled,readonly:e.readonly,"aria-labelledby":e.ariaLabelledby,"aria-label":e.ariaLabel,"aria-invalid":e.invalid||void 0,onFocus:t[0]||=function(){return a.onFocus&&a.onFocus.apply(a,arguments)},onBlur:t[1]||=function(){return a.onBlur&&a.onBlur.apply(a,arguments)},onChange:t[2]||=function(){return a.onChange&&a.onChange.apply(a,arguments)}},a.getPTOptions(`input`)),null,16,N),h(`div`,l({class:e.cx(`box`)},a.getPTOptions(`box`),{"data-p":a.dataP}),[h(`div`,l({class:e.cx(`icon`)},a.getPTOptions(`icon`),{"data-p":a.dataP}),null,16,F)],16,P)],16,M)}j.render=I;var L={class:`p-6 max-w-2xl mx-auto`},R={class:`flex items-center gap-3 mb-6`},z={class:`flex items-center gap-2 mb-0.5`},ee={class:`text-xs text-gray-600`},B={class:`text-2xl font-bold text-gray-800`},V={class:`bg-white rounded-xl shadow p-6`},H={class:`grid grid-cols-1 md:grid-cols-2 gap-4`},U={class:`md:col-span-2`},W={class:`text-red-500`},G={key:0},K={class:`text-red-500`},q={class:`block text-sm font-medium mb-1`},J={class:`text-red-500`},Y={class:`md:col-span-2`},X={class:`flex gap-3`},Z={class:`text-lg`},Q={class:`text-sm font-medium`},$={class:`text-red-500`},te={class:`flex justify-end gap-3 mt-6`},ne={__name:`Form`,props:{holding:Object},setup(e){let t=e,l=[{value:`CL`,label:`Chile`,flag:`🇨🇱`},{value:`UY`,label:`Uruguay`,flag:`🇺🇾`}],y=r({name:t.holding?.name??``,username:t.holding?.username??``,password:``,rutholding:t.holding?.rutholding??``,representantelegal:t.holding?.representantelegal??``,emailresponsable:t.holding?.emailresponsable??``,telefonoresponsable:t.holding?.telefonoresponsable??``,pais:t.holding?.pais??`CL`}),b=()=>{t.holding?y.put(route(`admin.holdings.update`,t.holding.id)):y.post(route(`admin.holdings.store`))};return(t,r)=>(c(),u(C,{title:e.holding?`Editar Holding - ${e.holding.name}`:`Crear Holding`},{default:o(()=>[h(`div`,L,[h(`div`,R,[d(s(n),{href:t.route(`admin.holdings`)},{default:o(()=>[d(s(S),{icon:`pi pi-arrow-left`,text:``})]),_:1},8,[`href`]),h(`div`,null,[h(`div`,z,[d(s(n),{href:t.route(`admin.index`),class:`text-gray-400 hover:text-blue-600 text-xs`},{default:o(()=>[...r[8]||=[f(`Administración`,-1)]]),_:1},8,[`href`]),r[10]||=h(`i`,{class:`pi pi-chevron-right text-gray-300 text-xs`},null,-1),d(s(n),{href:t.route(`admin.holdings`),class:`text-gray-400 hover:text-blue-600 text-xs`},{default:o(()=>[...r[9]||=[f(`Holdings`,-1)]]),_:1},8,[`href`]),r[11]||=h(`i`,{class:`pi pi-chevron-right text-gray-300 text-xs`},null,-1),h(`span`,ee,v(e.holding?`Editar`:`Crear`),1)]),h(`h1`,B,v(e.holding?e.holding.name:`Nuevo Holding`),1)])]),t.$page.props.flash?.success?(c(),u(s(w),{key:0,severity:`success`,class:`mb-4`},{default:o(()=>[f(v(t.$page.props.flash.success),1)]),_:1})):p(``,!0),h(`div`,V,[h(`form`,{onSubmit:i(b,[`prevent`])},[h(`div`,H,[h(`div`,U,[r[12]||=h(`label`,{class:`block text-sm font-medium mb-1`},`Nombre del Holding *`,-1),d(s(x),{modelValue:s(y).name,"onUpdate:modelValue":r[0]||=e=>s(y).name=e,class:g([`w-full`,{"p-invalid":s(y).errors.name}])},null,8,[`modelValue`,`class`]),h(`small`,W,v(s(y).errors.name),1)]),e.holding?p(``,!0):(c(),_(`div`,G,[r[13]||=h(`label`,{class:`block text-sm font-medium mb-1`},`Username *`,-1),d(s(x),{modelValue:s(y).username,"onUpdate:modelValue":r[1]||=e=>s(y).username=e,class:g([`w-full`,{"p-invalid":s(y).errors.username}])},null,8,[`modelValue`,`class`]),h(`small`,K,v(s(y).errors.username),1)])),h(`div`,null,[h(`label`,q,v(e.holding?`Nueva Contraseña (opcional)`:`Contraseña *`),1),d(s(x),{modelValue:s(y).password,"onUpdate:modelValue":r[2]||=e=>s(y).password=e,type:`password`,class:g([`w-full`,{"p-invalid":s(y).errors.password}]),placeholder:e.holding?`Dejar vacío para no cambiar`:``},null,8,[`modelValue`,`class`,`placeholder`]),h(`small`,J,v(s(y).errors.password),1)]),h(`div`,null,[r[14]||=h(`label`,{class:`block text-sm font-medium mb-1`},`RUT Holding`,-1),d(s(x),{modelValue:s(y).rutholding,"onUpdate:modelValue":r[3]||=e=>s(y).rutholding=e,class:`w-full`,placeholder:`12.345.678-9`},null,8,[`modelValue`])]),h(`div`,null,[r[15]||=h(`label`,{class:`block text-sm font-medium mb-1`},`Representante Legal`,-1),d(s(x),{modelValue:s(y).representantelegal,"onUpdate:modelValue":r[4]||=e=>s(y).representantelegal=e,class:`w-full`},null,8,[`modelValue`])]),h(`div`,null,[r[16]||=h(`label`,{class:`block text-sm font-medium mb-1`},`Email Responsable`,-1),d(s(x),{modelValue:s(y).emailresponsable,"onUpdate:modelValue":r[5]||=e=>s(y).emailresponsable=e,type:`email`,class:`w-full`},null,8,[`modelValue`])]),h(`div`,null,[r[17]||=h(`label`,{class:`block text-sm font-medium mb-1`},`Teléfono Responsable`,-1),d(s(x),{modelValue:s(y).telefonoresponsable,"onUpdate:modelValue":r[6]||=e=>s(y).telefonoresponsable=e,class:`w-full`},null,8,[`modelValue`])]),h(`div`,Y,[r[18]||=h(`label`,{class:`block text-sm font-medium mb-1`},`País *`,-1),h(`div`,X,[(c(),_(m,null,a(l,e=>h(`label`,{key:e.value,class:g([`flex items-center gap-2 border rounded-lg px-4 py-2.5 cursor-pointer transition`,s(y).pais===e.value?`border-blue-500 bg-blue-50 text-blue-700`:`border-gray-200 hover:border-gray-300 text-gray-600`])},[d(s(j),{modelValue:s(y).pais,"onUpdate:modelValue":r[7]||=e=>s(y).pais=e,value:e.value},null,8,[`modelValue`,`value`]),h(`span`,Z,v(e.flag),1),h(`span`,Q,v(e.label),1)],2)),64))]),h(`small`,$,v(s(y).errors.pais),1)])]),h(`div`,te,[d(s(n),{href:t.route(`admin.holdings`)},{default:o(()=>[d(s(S),{label:`Cancelar`,severity:`secondary`,type:`button`})]),_:1},8,[`href`]),d(s(S),{label:e.holding?`Guardar Cambios`:`Crear Holding`,icon:`pi pi-save`,type:`submit`,loading:s(y).processing,style:{"background-color":`#3452ff`,"border-color":`#3452ff`}},null,8,[`label`,`loading`])])],32)])])]),_:1},8,[`title`]))}};export{ne as default};