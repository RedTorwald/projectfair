import{c,b as i,S as d,B as p}from"./index.4576b3b5.js";import{b as l,z as m,h as a,c as f,e as h,f as v,l as S,t as _}from"./vendor.66a6c8d1.js";const b=_(" \u041F\u043E\u0434\u0430\u0442\u044C \u0437\u0430\u044F\u0432\u043A\u0443 "),C=l({props:{project:null,variant:{default:"outlined"}},setup(r){const t=r,o=c(),n=i(),u=m(()=>{var e;return t.project.state.id===d.ArchivedState||((e=n.profileData)==null?void 0:e.is_teacher)});return(e,s)=>a(u)?S("",!0):(f(),h(p,{key:0,case:"uppercase",variant:t.variant,disabled:a(o).loading,onClick:s[0]||(s[0]=B=>a(o).openParticipationModal(t.project))},{default:v(()=>[b]),_:1},8,["variant","disabled"]))}});export{C as _};