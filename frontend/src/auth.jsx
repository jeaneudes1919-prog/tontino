import {createContext,useContext,useEffect,useState} from "react";import {api} from "./api.js";
const Ctx=createContext();export const useAuth=()=>useContext(Ctx);
export function AuthProvider({children}){const [user,setUser]=useState(null);const [loading,setLoading]=useState(!!localStorage.getItem("tontino_token"));
useEffect(()=>{if(localStorage.getItem("tontino_token"))api.get("/me").then(setUser).catch(()=>localStorage.removeItem("tontino_token")).finally(()=>setLoading(false));
const h=()=>setUser(null);window.addEventListener("tontino:logout",h);return()=>window.removeEventListener("tontino:logout",h)},[]);
const done=r=>{localStorage.setItem("tontino_token",r.token);setUser(r.user)};
const login=async(email,password)=>done(await api.post("/auth/login",{email,password}));
const register=async f=>done(await api.post("/auth/register",f));
const logout=async()=>{try{await api.post("/auth/logout")}catch{}localStorage.removeItem("tontino_token");setUser(null)};
return <Ctx.Provider value={{user,setUser,loading,login,register,logout}}>{children}</Ctx.Provider>}
