import{D as e,F as t,H as n,T as r,b as i,c as a,g as o,h as s,l as c,r as l,s as u,st as d,u as f,ut as p}from"./runtime-core.esm-bundler-CB8jxRX2.js";import{Q as m,n as h}from"./service-vOrQU6dP.js";import{c as g,h as _,s as v,t as y}from"./button-D72i7LJK.js";import{i as b,m as x,r as S}from"./app-DTw3NSFx.js";import{t as C}from"./AppLayout-DxvwdvjG.js";import{t as w}from"./message-wnRieeQ1.js";var T=h.extend({name:`radiobutton`,style:`
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
`,classes:{root:function(e){var t=e.instance,n=e.props;return[`p-radiobutton p-component`,{"p-radiobutton-checked":t.checked,"p-disabled":n.disabled,"p-invalid":t.$pcRadioButtonGroup?t.$pcRadioButtonGroup.$invalid:t.$invalid,"p-variant-filled":t.$variant===`filled`,"p-radiobutton-sm p-inputfield-sm":n.size===`small`,"p-radiobutton-lg p-inputfield-lg":n.size===`large`}]},box:`p-radiobutton-box`,input:`p-radiobutton-input`,icon:`p-radiobutton-icon`}}),E={name:`BaseRadioButton`,extends:g,props:{value:null,binary:Boolean,readonly:{type:Boolean,default:!1},tabindex:{type:Number,default:null},inputId:{type:String,default:null},inputClass:{type:[String,Object],default:null},inputStyle:{type:Object,default:null},ariaLabelledby:{type:String,default:null},ariaLabel:{type:String,default:null}},style:T,provide:function(){return{$pcRadioButton:this,$parentInstance:this}}};function D(e){"@babel/helpers - typeof";return D=typeof Symbol==`function`&&typeof Symbol.iterator==`symbol`?function(e){return typeof e}:function(e){return e&&typeof Symbol==`function`&&e.constructor===Symbol&&e!==Symbol.prototype?`symbol`:typeof e},D(e)}function O(e,t,n){return(t=k(t))in e?Object.defineProperty(e,t,{value:n,enumerable:!0,configurable:!0,writable:!0}):e[t]=n,e}function k(e){var t=A(e,`string`);return D(t)==`symbol`?t:t+``}function A(e,t){if(D(e)!=`object`||!e)return e;var n=e[Symbol.toPrimitive];if(n!==void 0){var r=n.call(e,t);if(D(r)!=`object`)return r;throw TypeError(`@@toPrimitive must return a primitive value.`)}return(t===`string`?String:Number)(e)}var j={name:`RadioButton`,extends:E,inheritAttrs:!1,emits:[`change`,`focus`,`blur`],inject:{$pcRadioButtonGroup:{default:void 0}},methods:{getPTOptions:function(e){return(e===`root`?this.ptmi:this.ptm)(e,{context:{checked:this.checked,disabled:this.disabled}})},onChange:function(e){if(!this.disabled&&!this.readonly){var t=this.binary?!this.checked:this.value;this.$pcRadioButtonGroup?this.$pcRadioButtonGroup.writeValue(t,e):this.writeValue(t,e),this.$emit(`change`,e)}},onFocus:function(e){this.$emit(`focus`,e)},onBlur:function(e){var t,n;this.$emit(`blur`,e),(t=(n=this.formField).onBlur)==null||t.call(n,e)}},computed:{groupName:function(){return this.$pcRadioButtonGroup?this.$pcRadioButtonGroup.groupName:this.$formName},checked:function(){var e=this.$pcRadioButtonGroup?this.$pcRadioButtonGroup.d_value:this.d_value;return e!=null&&(this.binary?!!e:m(e,this.value))},dataP:function(){return _(O({invalid:this.$invalid,checked:this.checked,disabled:this.disabled,filled:this.$variant===`filled`},this.size,this.size))}}},M=[`data-p-checked`,`data-p-disabled`,`data-p`],N=[`id`,`value`,`name`,`checked`,`tabindex`,`disabled`,`readonly`,`aria-labelledby`,`aria-label`,`aria-invalid`],P=[`data-p`],F=[`data-p`];function I(e,t,n,a,o,s){return r(),f(`div`,i({class:e.cx(`root`)},s.getPTOptions(`root`),{"data-p-checked":s.checked,"data-p-disabled":e.disabled,"data-p":s.dataP}),[u(`input`,i({id:e.inputId,type:`radio`,class:[e.cx(`input`),e.inputClass],style:e.inputStyle,value:e.value,name:s.groupName,checked:s.checked,tabindex:e.tabindex,disabled:e.disabled,readonly:e.readonly,"aria-labelledby":e.ariaLabelledby,"aria-label":e.ariaLabel,"aria-invalid":e.invalid||void 0,onFocus:t[0]||=function(){return s.onFocus&&s.onFocus.apply(s,arguments)},onBlur:t[1]||=function(){return s.onBlur&&s.onBlur.apply(s,arguments)},onChange:t[2]||=function(){return s.onChange&&s.onChange.apply(s,arguments)}},s.getPTOptions(`input`)),null,16,N),u(`div`,i({class:e.cx(`box`)},s.getPTOptions(`box`),{"data-p":s.dataP}),[u(`div`,i({class:e.cx(`icon`)},s.getPTOptions(`icon`),{"data-p":s.dataP}),null,16,F)],16,P)],16,M)}j.render=I;var L={class:`p-6 max-w-2xl mx-auto`},R={class:`flex items-center gap-3 mb-6`},z={class:`flex items-center gap-2 mb-0.5`},ee={class:`text-xs text-gray-600`},B={class:`text-2xl font-bold text-gray-800`},V={class:`bg-white rounded-xl shadow p-6`},H={class:`grid grid-cols-1 md:grid-cols-2 gap-4`},U={class:`md:col-span-2`},W={class:`text-red-500`},G={key:0},K={class:`text-red-500`},q={class:`block text-sm font-medium mb-1`},J={class:`text-red-500`},Y={class:`md:col-span-2`},X={class:`flex gap-3`},Z={class:`text-lg`},Q={class:`text-sm font-medium`},$={class:`text-red-500`},te={class:`flex justify-end gap-3 mt-6`},ne={__name:`Form`,props:{holding:Object},setup(i){let m=i,h=[{value:`CL`,label:`Chile`,flag:`🇨🇱`},{value:`UY`,label:`Uruguay`,flag:`🇺🇾`}],g=b({name:m.holding?.name??``,username:m.holding?.username??``,password:``,rutholding:m.holding?.rutholding??``,representantelegal:m.holding?.representantelegal??``,emailresponsable:m.holding?.emailresponsable??``,telefonoresponsable:m.holding?.telefonoresponsable??``,pais:m.holding?.pais??`CL`}),_=()=>{m.holding?g.put(route(`admin.holdings.update`,m.holding.id)):g.post(route(`admin.holdings.store`))};return(m,b)=>(r(),a(C,{title:i.holding?`Editar Holding - ${i.holding.name}`:`Crear Holding`},{default:t(()=>[u(`div`,L,[u(`div`,R,[o(n(S),{href:m.route(`admin.holdings`)},{default:t(()=>[o(n(y),{icon:`pi pi-arrow-left`,text:``})]),_:1},8,[`href`]),u(`div`,null,[u(`div`,z,[o(n(S),{href:m.route(`admin.index`),class:`text-gray-400 hover:text-blue-600 text-xs`},{default:t(()=>[...b[8]||=[s(`Administración`,-1)]]),_:1},8,[`href`]),b[10]||=u(`i`,{class:`pi pi-chevron-right text-gray-300 text-xs`},null,-1),o(n(S),{href:m.route(`admin.holdings`),class:`text-gray-400 hover:text-blue-600 text-xs`},{default:t(()=>[...b[9]||=[s(`Holdings`,-1)]]),_:1},8,[`href`]),b[11]||=u(`i`,{class:`pi pi-chevron-right text-gray-300 text-xs`},null,-1),u(`span`,ee,p(i.holding?`Editar`:`Crear`),1)]),u(`h1`,B,p(i.holding?i.holding.name:`Nuevo Holding`),1)])]),m.$page.props.flash?.success?(r(),a(n(w),{key:0,severity:`success`,class:`mb-4`},{default:t(()=>[s(p(m.$page.props.flash.success),1)]),_:1})):c(``,!0),u(`div`,V,[u(`form`,{onSubmit:x(_,[`prevent`])},[u(`div`,H,[u(`div`,U,[b[12]||=u(`label`,{class:`block text-sm font-medium mb-1`},`Nombre del Holding *`,-1),o(n(v),{modelValue:n(g).name,"onUpdate:modelValue":b[0]||=e=>n(g).name=e,class:d([`w-full`,{"p-invalid":n(g).errors.name}])},null,8,[`modelValue`,`class`]),u(`small`,W,p(n(g).errors.name),1)]),i.holding?c(``,!0):(r(),f(`div`,G,[b[13]||=u(`label`,{class:`block text-sm font-medium mb-1`},`Username *`,-1),o(n(v),{modelValue:n(g).username,"onUpdate:modelValue":b[1]||=e=>n(g).username=e,class:d([`w-full`,{"p-invalid":n(g).errors.username}])},null,8,[`modelValue`,`class`]),u(`small`,K,p(n(g).errors.username),1)])),u(`div`,null,[u(`label`,q,p(i.holding?`Nueva Contraseña (opcional)`:`Contraseña *`),1),o(n(v),{modelValue:n(g).password,"onUpdate:modelValue":b[2]||=e=>n(g).password=e,type:`password`,class:d([`w-full`,{"p-invalid":n(g).errors.password}]),placeholder:i.holding?`Dejar vacío para no cambiar`:``},null,8,[`modelValue`,`class`,`placeholder`]),u(`small`,J,p(n(g).errors.password),1)]),u(`div`,null,[b[14]||=u(`label`,{class:`block text-sm font-medium mb-1`},`RUT Holding`,-1),o(n(v),{modelValue:n(g).rutholding,"onUpdate:modelValue":b[3]||=e=>n(g).rutholding=e,class:`w-full`,placeholder:`12.345.678-9`},null,8,[`modelValue`])]),u(`div`,null,[b[15]||=u(`label`,{class:`block text-sm font-medium mb-1`},`Representante Legal`,-1),o(n(v),{modelValue:n(g).representantelegal,"onUpdate:modelValue":b[4]||=e=>n(g).representantelegal=e,class:`w-full`},null,8,[`modelValue`])]),u(`div`,null,[b[16]||=u(`label`,{class:`block text-sm font-medium mb-1`},`Email Responsable`,-1),o(n(v),{modelValue:n(g).emailresponsable,"onUpdate:modelValue":b[5]||=e=>n(g).emailresponsable=e,type:`email`,class:`w-full`},null,8,[`modelValue`])]),u(`div`,null,[b[17]||=u(`label`,{class:`block text-sm font-medium mb-1`},`Teléfono Responsable`,-1),o(n(v),{modelValue:n(g).telefonoresponsable,"onUpdate:modelValue":b[6]||=e=>n(g).telefonoresponsable=e,class:`w-full`},null,8,[`modelValue`])]),u(`div`,Y,[b[18]||=u(`label`,{class:`block text-sm font-medium mb-1`},`País *`,-1),u(`div`,X,[(r(),f(l,null,e(h,e=>u(`label`,{key:e.value,class:d([`flex items-center gap-2 border rounded-lg px-4 py-2.5 cursor-pointer transition`,n(g).pais===e.value?`border-blue-500 bg-blue-50 text-blue-700`:`border-gray-200 hover:border-gray-300 text-gray-600`])},[o(n(j),{modelValue:n(g).pais,"onUpdate:modelValue":b[7]||=e=>n(g).pais=e,value:e.value},null,8,[`modelValue`,`value`]),u(`span`,Z,p(e.flag),1),u(`span`,Q,p(e.label),1)],2)),64))]),u(`small`,$,p(n(g).errors.pais),1)])]),u(`div`,te,[o(n(S),{href:m.route(`admin.holdings`)},{default:t(()=>[o(n(y),{label:`Cancelar`,severity:`secondary`,type:`button`})]),_:1},8,[`href`]),o(n(y),{label:i.holding?`Guardar Cambios`:`Crear Holding`,icon:`pi pi-save`,type:`submit`,loading:n(g).processing,style:{"background-color":`#3452ff`,"border-color":`#3452ff`}},null,8,[`label`,`loading`])])],32)])])]),_:1},8,[`title`]))}};export{ne as default};