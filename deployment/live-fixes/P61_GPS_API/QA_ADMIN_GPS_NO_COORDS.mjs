/**
 * P61 authenticated Admin GPS route validation, zero real coordinates or
 * business location writes. Invalid values MUST produce 422 (not former 403).
 */
const root=(process.env.STAGING_BASE_URL||'https://staging.assammotors.com').replace(/\/$/,'');
const email=process.env.STAGING_ADMIN_LOGIN, password=process.env.STAGING_ADMIN_PASSWORD;
if(!email||!password)throw Error('Staging test auth missing');
const req=async (url,opts={})=>fetch(root+url,{...opts,signal:AbortSignal.timeout(15000)});
const login=await req('/api/v1/auth/login',{method:'POST',
  headers:{'Content-Type':'application/json','Accept':'application/json'},
  body:JSON.stringify({email,password,device_name:'P61 Role Gate Validation Only'})});
const reply=await login.json().catch(()=>({}));
if(login.status!==200||!reply.token||!reply.user?.is_admin)
 throw Error('P61_ADMIN_AUTH_UNAVAILABLE HTTP '+login.status+' ROLE '+(reply?.user?.role||'unknown'));
const token=String(reply.token);
try{
 const r=await req('/api/v1/staff/location',{method:'POST',
 headers:{'Accept':'application/json','Content-Type':'application/json',Authorization:'Bearer '+token},
 body:JSON.stringify({latitude:'INVALID-NO-COORDINATES',source:'ANDROID_FOREGROUND_NATIVE_60S',is_mocked:false})});
 const text=await r.text();
 const rejectedValidation=r.status===422&&
   (text.includes('latitude')||text.includes('longitude')||text.includes('accuracy_m'));
 console.log('P61_ADMIN_GPS_ROUTE_REACHABLE_INVALID_COORDS_REJECTED '+(rejectedValidation?'PASS':'FAIL')+
  ' HTTP '+r.status);
 if(!rejectedValidation)throw Error('P61 GPS route validation check failed: HTTP '+r.status);
 console.log('P61_ADMIN_GPS_PERMISSION_GATE_PASS_NO_REAL_LOCATION_POSTED');
}finally{
 await req('/api/v1/auth/logout',{method:'POST',headers:{
   'Accept':'application/json',Authorization:'Bearer '+token}}).catch(()=>{});
}