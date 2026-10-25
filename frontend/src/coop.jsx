import {createContext,useContext,useMemo} from "react";import {Navigate,useParams} from "react-router-dom";import {api} from "./api.js";import {useLoad} from "./lib.js";import {Skeleton} from "./ui.jsx";
const Ctx=createContext();export const useCoop=()=>useContext(Ctx);
export function CoopProvider({children}){const {coopId}=useParams();const [coops,reload]=useLoad(()=>api.get("/my/cooperatives"),[]);
const id=+coopId;const current=coops?.find(c=>c.id===id);
const value=useMemo(()=>({id,coops,current,reload,role:current?.role,membershipId:current?.membership_id,isManager:["admin","treasurer"].includes(current?.role),isAdmin:current?.role==="admin",
c:{get:(u,p)=>api.get(`/coops/${id}${u}`,p),post:(u,b)=>api.post(`/coops/${id}${u}`,b),put:(u,b)=>api.put(`/coops/${id}${u}`,b),patch:(u,b)=>api.patch(`/coops/${id}${u}`,b),del:u=>api.del(`/coops/${id}${u}`),path:u=>`/coops/${id}${u}`}}),[id,coops]);
if(!coops)return <div className="mx-auto max-w-5xl p-6"><Skeleton className="h-80"/></div>;
if(!current)return <Navigate to="/" replace/>;
return <Ctx.Provider value={value}>{children}</Ctx.Provider>}
