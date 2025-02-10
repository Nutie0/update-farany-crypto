using Microsoft.Extensions.Caching.Memory;
using System;

public class PinCacheService
{
    private readonly IMemoryCache _cache;

    public PinCacheService(IMemoryCache cache)
    {
        _cache = cache;
    }

    public string GetPin(string email)
    {
        return _cache.TryGetValue(email, out string pin) ? pin : null;
    }

    public void SavePin(string email, string pin)
    {
        var expirationTime = DateTime.Now.AddMinutes(10);  // Expiration dans 10 minutes
        _cache.Set(email, pin, expirationTime);
    }

    public void DeletePin(string email)
    {
        _cache.Remove(email);
    }
}
