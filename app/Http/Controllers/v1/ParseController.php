<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UtilsController;
use App\Models\Account;
use App\Models\FileList;
use App\Models\Group;
use App\Models\Record;
use App\Models\Token;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ParseController extends Controller
{
    public function getConfig(Request $request)
    {
        $config = config("94list");

        return ResponseController::success([
            "show_announce"    => $config["announce"] !== null && $config["announce"] !== "",
            "announce"         => $config["announce"],
            "user_agent"       => $config["user_agent"],
            "debug"            => config("app.debug"),
            "max_once"         => $config["max_once"],
            "have_account"     => self::getRandomCookie(["超级会员"], false)->getData(true)["code"] === 200,
            "have_login"       => Auth::check(),
            "need_inv_code"    => $config["need_inv_code"],
            "need_password"    => $config["password"] !== "",
            "show_copyright"   => $config["show_copyright"],
            "custom_copyright" => $config["custom_copyright"],
            "min_single_file"  => $config["min_single_file"],
            "token_mode"       => $config["token_mode"],
            "button_link"      => $config["button_link"],
            // "prov"             => self::getProvinceFromIP(UtilsController::getIp())
        ]);
    }

    public function getProvinceFromIP($ip)
    {
        $prov = self::_getProvinceFromIP($ip);
        if ($prov === null) return null;

        // 省份标准名称映射表
        $provinces = [
            "北京"   => "北京市",
            "天津"   => "天津市",
            "上海"   => "上海市",
            "重庆"   => "重庆市",
            "河北"   => "河北省",
            "山西"   => "山西省",
            "内蒙古" => "内蒙古自治区",
            "辽宁"   => "辽宁省",
            "吉林"   => "吉林省",
            "黑龙江" => "黑龙江省",
            "江苏"   => "江苏省",
            "浙江"   => "浙江省",
            "安徽"   => "安徽省",
            "福建"   => "福建省",
            "江西"   => "江西省",
            "山东"   => "山东省",
            "河南"   => "河南省",
            "湖北"   => "湖北省",
            "湖南"   => "湖南省",
            "广东"   => "广东省",
            "广西"   => "广西壮族自治区",
            "海南"   => "海南省",
            "四川"   => "四川省",
            "贵州"   => "贵州省",
            "云南"   => "云南省",
            "西藏"   => "西藏自治区",
            "陕西"   => "陕西省",
            "甘肃"   => "甘肃省",
            "青海"   => "青海省",
            "宁夏"   => "宁夏回族自治区",
            "新疆"   => "新疆维吾尔自治区",
            "香港"   => "香港特别行政区",
            "澳门"   => "澳门特别行政区",
            "台湾"   => "台湾省"
        ];

        // 去除多余的空白字符
        $name = trim($prov);

        // 匹配并返回标准省份名称
        foreach ($provinces as $key => $standardName) {
            if (str_contains($name, $key)) return $standardName;
        }

        // 若无匹配则返回原名
        return null;
    }

    public function _getProvinceFromIP($ip)
    {
        if ($ip === "0.0.0.0") return "上海市";

        $http = new Client([
            "headers" => [
                "User-Agent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36 Edg/125.0.0.0"
            ]
        ]);

        try {
            $res      = $http->get("https://api.qjqq.cn/api/district", ["query" => ["ip" => $ip]]);
            $response = JSON::decode($res->getBody()->getContents());

            if (isset($response["data"]["country"]) && isset($response["data"]["prov"])) {
                if (config("94list.limit_cn")) {
                    if ($response["data"]["country"] !== "中国") {
                        return false;
                    }
                }

                return $response["data"]["prov"] !== "" ? $response["data"]["prov"] : null;
            }
        } catch (GuzzleException $e) {
        }

        try {
            $res      = $http->get("https://www.ip.cn/api/index", ["query" => ["ip" => $ip, "type" => 1]]);
            $response = JSON::decode($res->getBody()->getContents());

            if (isset($response["address"])) {
                if (config("94list.limit_cn")) {
                    if (str_contains($response["address"], "中国")) {
                        return false;
                    }
                }

                return $response["address"] !== "" ? $response["address"] : null;
            }
        } catch (GuzzleException $e) {
        }

        try {
            $res      = $http->get("https://api.vore.top/api/IPdata", ["query" => ["ip" => $ip]]);
            $response = JSON::decode($res->getBody()->getContents());

            if (isset($response["ipinfo"]["cnip"]) && isset($response["ipdata"]["info1"])) {
                if (config("94list.limit_cn")) {
                    if (!$response["ipinfo"]["cnip"]) {
                        return false;
                    }
                }

                return $response["ipdata"]["info1"] !== "" ? $response["ipdata"]["info1"] : null;
            }
        } catch (GuzzleException $e) {
        }

        try {
            $res      = $http->get("https://qifu.baidu.com/ip/geo/v1/district", ["query" => ["ip" => $ip]]);
            $response = JSON::decode($res->getBody()->getContents());

            if (isset($response["data"]["country"]) && isset($response["data"]["prov"])) {
                if (config("94list.limit_cn")) {
                    if ($response["data"]["country"] !== "中国") {
                        return false;
                    }
                }

                return $response["data"]["prov"] !== "" ? $response["data"]["prov"] : null;
            }
        } catch (GuzzleException $e) {
        }

        return null;
    }

    public function getRandomCookie($vipType = ["超级会员"], $makeNew = true)
    {
        $ip   = UtilsController::getIp();
        $prov = self::getProvinceFromIP($ip);
        if ($prov === false) return ResponseController::unsupportNotCNCountry();

        $vipType = is_array($vipType) ? $vipType : [$vipType];

        if (in_array("超级会员", $vipType)) {
            // 禁用不可用的账户
            $banAccounts = Account::query()
                                  ->where(["switch" => 1, "vip_type" => "超级会员",])
                                  ->whereDate("svip_end_at", "<", now())
                                  ->whereTime("svip_end_at", "<", now())
                                  ->get();

            $updateFailedAccounts = [];

            if ($banAccounts->count() !== 0) {
                // 更新账户状态
                foreach ($banAccounts as $account) {
                    $updateRes  = AccountController::updateAccountInfo($account["id"]);
                    $updateData = $updateRes->getData(true);
                    if ($updateData["code"] !== 200) {
                        $account->update([
                            "switch" => 0,
                            "reason" => $updateData["message"]
                        ]);
                        $updateFailedAccounts[] = $account->toJson();
                    }
                    sleep(1);
                }

                if (config("mail.switch")) {
                    try {
                        Mail::raw("亲爱的" . config("mail.to.name") . ":\n\t有账户已过期,详见:" . JSON::encode($updateFailedAccounts), function ($message) {
                            $message->to(config("mail.to.address"))->subject("有账户过期了~");
                        });
                    } catch (Exception $e) {
//                        return ResponseController::sendMailFailed($e->getMessage());
                    }
                }
            }
        }

        // 判斷是否獲取到了省份
        if ($prov !== null) {
            $account = Account::query()
                              ->where("switch", 1)
                              ->whereIn("vip_type", $vipType)
                              ->leftJoin('records', function ($join) {
                                  $join->on('accounts.id', '=', 'records.account_id')
                                       ->whereDate('records.created_at', now());
                              })
                              ->select('accounts.*', DB::raw('IFNULL(SUM(records.size), 0) as total_size'))
                              ->groupBy('accounts.id')
                              ->having('total_size', '<', config("94list.max_filesize"))
                              ->inRandomOrder()
                              ->where("prov", $prov)
                              ->first();

            if ($account === null) {
                $account = Account::query()
                                  ->where("switch", 1)
                                  ->whereIn("vip_type", $vipType)
                                  ->leftJoin('records', function ($join) {
                                      $join->on('accounts.id', '=', 'records.account_id')
                                           ->whereDate('records.created_at', now());
                                  })
                                  ->select('accounts.*', DB::raw('IFNULL(SUM(records.size), 0) as total_size'))
                                  ->groupBy('accounts.id')
                                  ->having('total_size', '<', config("94list.max_filesize"))
                                  ->inRandomOrder()
                                  ->whereNull("prov")
                                  ->first();

                if ($makeNew) {
                    $account?->update([
                        "prov" => $prov,
                    ]);
                }
            }
        } else {
            $account = Account::query()
                              ->where("switch", 1)
                              ->whereIn("vip_type", $vipType)
                              ->leftJoin('records', function ($join) {
                                  $join->on('accounts.id', '=', 'records.account_id')
                                       ->whereDate('records.created_at', now());
                              })
                              ->select('accounts.*', DB::raw('IFNULL(SUM(records.size), 0) as total_size'))
                              ->groupBy('accounts.id')
                              ->having('total_size', '<', config("94list.max_filesize"))
                              ->inRandomOrder()
                              ->whereNull("prov")
                              ->first();
        }

        if (!$account) return ResponseController::svipAccountIsNotEnough(true, $vipType);

        return ResponseController::success($account);
    }

    public function decodeSceKey($seckey)
    {
        $seckey = str_replace("-", "+", $seckey);
        $seckey = str_replace("~", "=", $seckey);
        return str_replace("_", "/", $seckey);
    }

    public function getFileList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "surl"  => "required|string",
            "dir"   => "required|string",
            "pwd"   => "string",
            "page"  => "numeric",
            "num"   => "numeric",
            "order" => Rule::in(["time", "filename"])
        ]);

        if ($validator->fails()) return ResponseController::paramsError();

        try {
            $http     = new Client([
                "headers" => [
                    "User-Agent" => config("94list.fake_wx_user_agent"),
                    "Cookie"     => config("94list.fake_cookie"),
                    "Referer"    => "https://pan.baidu.com/disk/home"
                ]
            ]);
            $res      = $http->post("https://pan.baidu.com/share/wxlist", [
                "query"       => [
                    "channel"    => "weixin",
                    "version"    => "2.9.6",
                    "clienttype" => 25,
                    "web"        => 1,
                    "qq-pf-to"   => "pcqq.c2c"
                ],
                "form_params" => [
                    "shorturl" => $request["surl"],
                    "dir"      => $request["dir"],
                    "root"     => $request["dir"] === "/" ? 1 : 0,
                    "pwd"      => $request["pwd"] ?? "",
                    "page"     => $request["page"] ?? 1,
                    "num"      => $request["num"] ?? 1000,
                    "order"    => $request["order"] ?? "time"
                ]
            ]);
            $response = JSON::decode($res->getBody()->getContents());
        } catch (RequestException $e) {
            $response = $e->hasResponse() ? JSON::decode($e->getResponse()->getBody()->getContents()) : null;
        } catch (GuzzleException $e) {
            return ResponseController::networkError("获取文件列表");
        }

        $errno = $response["errtype"] ?? ($response["errno"] ?? "未知");

        if ($errno === 0) {
            if (!isset($response["data"]["uk"]) | !isset($response["data"]["shareid"]) | !isset($response["data"]["seckey"]) | !isset($response["data"]["list"])) {
                $errno = "请检查链接是否有效并重试";
            } else {
                // 保存所有文件到数据库
                foreach ($response["data"]["list"] as $file) {
                    if ($file["isdir"] == 1 || $file["isdir"] == "1" || !isset($file["fs_id"]) || !isset($file["md5"])) continue;
                    $find = FileList::query()->firstWhere("fs_id", $file["fs_id"]);
                    if ($find) {
                        if ($find["md5"] !== $file["md5"]) {
                            $find->update([
                                "size" => $file["size"],
                                "md5"  => $file["md5"]
                            ]);
                        }
                    } else {
                        FileList::query()->create([
                            "fs_id" => $file["fs_id"],
                            "size"  => $file["size"],
                            "md5"   => $file["md5"]
                        ]);
                    }
                }
            }
        }

        return match ($errno) {
            0                     => ResponseController::success([
                "uk"      => $response["data"]["uk"],
                "shareid" => $response["data"]["shareid"],
                "randsk"  => self::decodeSceKey($response["data"]["seckey"]),
                "list"    => $response["data"]["list"]
            ]),
            "mis_105"             => ResponseController::fileNotExists(),
            "mispw_9", "mispwd-9" => ResponseController::pwdWrong(),
            "mis_2", "mis_4"      => ResponseController::pathNotExists(),
            5                     => ResponseController::linkWrongOrPathNotExists(),
            3                     => ResponseController::linkNotValid(),
            10                    => ResponseController::linkIsOutDate(),
            8001, 9013, 9019      => ResponseController::cookieError($errno),
            default               => ResponseController::getFileListError($errno),
        };
    }

    public function checkLimit(Request $request)
    {
        if (config("94list.token_mode") && isset($request["token"]) && $request["token"] !== "") {
            $validator = Validator::make($request->all(), [
                "token" => "required|string",
            ]);

            if ($validator->fails()) return ResponseController::paramsError();

            $token = Token::query()->firstWhere("name", $request["token"]);
            if (!$token) return ResponseController::TokenNotExists();

            // 检查是否已经过期
            if ($token["expired_at"] !== null && $token["expired_at"] < now()) return ResponseController::TokenExpired();

            $records = Record::withTrashed()
                             ->where("user_id", -$token["id"])
                             ->get();

            if ($records->count() >= $token["count"] || $records->sum("size") >= $token["size"] * 1073741824) return ResponseController::TokenQuotaHasBeenUsedUp();

            return ResponseController::success([
                "group_name" => $token["name"],
                "count"      => $token["count"] - $records->count(),
                "size"       => $token["size"] * 1073741824 - $records->sum("size"),
                "expired_at" => $token["expired_at"] ?? "未使用"
            ]);
        }

        // 获取今日解析数量
        $group = Group::query()
                      ->find(Auth::check() ? Auth::user()["group_id"] : -1);

        $records = Record::withTrashed()
                         ->where("ip", UtilsController::getIp())
                         ->whereDate("created_at", now())
                         ->get();

        if ($records->count() >= $group["count"] || $records->sum("size") >= $group["size"] * 1073741824) return ResponseController::groupQuotaHasBeenUsedUp();

        return ResponseController::success([
            "group_name" => $group["name"],
            "count"      => $group["count"] - $records->count(),
            "size"       => $group["size"] * 1073741824 - $records->sum("size")
        ]);
    }

    public function getVcode()
    {
        try {
            $http = new Client([
                "headers" => [
                    "User-Agent" => config("94list.fake_user_agent"),
                    "Cookie"     => config("94list.fake_cookie"),
                    "Host"       => "pan.baidu.com",
                    "Origin"     => "https://pan.baidu.com",
                    "Referer"    => "https://pan.baidu.com/disk/home"
                ]
            ]);

            $response = $http->get("https://pan.baidu.com/api/getvcode", [
                "query" => [
                    "prod"       => "pan",
                    "channel"    => "chunlei",
                    "web"        => 1,
                    "app_id"     => 250528,
                    "clienttype" => 0
                ]
            ]);

            $response = JSON::decode($response->getBody());

            return ResponseController::success([
                "img"   => $response["img"],
                "vcode" => $response["vcode"]
            ]);
        } catch (RequestException $e) {
            return ResponseController::getVcodeError();
        } catch (GuzzleException $e) {
            return ResponseController::networkError("获取vcode");
        }
    }

    public function getDownloadLinks(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "fs_ids"   => "required|array",
            "fs_ids.*" => "required|numeric"
        ]);

        if ($validator->fails()) return ResponseController::paramsError();

        // 不能超出最大解析限制
        if (count($request["fs_ids"]) > config("94list.max_once")) return ResponseController::linksOverloaded();

        // 检查限制还能不能解析
        $checkLimitRes  = self::checkLimit($request);
        $checkLimitData = $checkLimitRes->getData(true);
        if ($checkLimitData["code"] !== 200) return $checkLimitRes;

        // 检查文件数量是否符合用户组配额
        if (count($request["fs_ids"]) > $checkLimitData["data"]["count"]) return ResponseController::groupQuotaCountIsNotEnough();

        // 获取文件列表
        $fileList = FileList::query()->whereIn("fs_id", $request["fs_ids"])->get();

        if (count($fileList) !== count($request["fs_ids"])) return ResponseController::unknownFsId();

        foreach ($fileList as $file) {
            if ($file["size"] < config("94list.min_single_file")) {
                $request["fs_ids"] = array_filter($request["fs_ids"], fn($v) => $v === $file["fs_ids"]);
            }
        }

        if (count($request["fs_ids"]) === 0) return ResponseController::nullFile();

        // 检查文件大小是否符合用户组配额
        if (collect($fileList)->sum("size") > $checkLimitData["data"]["size"]) return ResponseController::groupQuotaSizeIsNotEnough();

        $parse_mode = config("94list.parse_mode");

        return match ($parse_mode) {
            1       => self::getDownloadLinksByDisk($request),
            2       => self::getDownloadLinksByShare($request),
            3, 4    => self::getDownloadLinksByShareV2($request, $parse_mode),
            default => ResponseController::unknownParseMode()
        };
    }

    public function getDownloadLinksByDisk(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "randsk"   => "required|string",
            "uk"       => "required|numeric",
            "shareid"  => "required|numeric",
            "fs_ids"   => "required|array",
            "fs_ids.*" => "required|numeric",
            "url"      => "required|string"
        ]);

        if ($validator->fails()) return ResponseController::paramsError();

        $cookie     = self::getRandomCookie();
        $cookieData = $cookie->getData(true);
        if ($cookieData["code"] !== 200) return $cookie;

        $ua = config("94list.user_agent");

        try {
            $http = new Client();

            $res      = $http->post(config("94list.main_server") . "/api/parseUrl", [
                "json" => [
                    "type"     => 1,
                    "fsidlist" => $request["fs_ids"],
                    "code"     => config("94list.code"),
                    "cookie"   => $cookieData["data"]["cookie"],
                    "randsk"   => $request["randsk"],
                    "uk"       => $request["uk"],
                    "shareid"  => $request["shareid"],
                    "ua"       => $ua,
                    "url"      => $request["url"]
                ]
            ]);
            $response = JSON::decode($res->getBody()->getContents());
        } catch (RequestException $e) {
            $response = JSON::decode($e->getResponse()->getBody()->getContents());
            $reason   = $response["message"] ?? "未知原因,请重试";
            if ($reason !== "授权码已过期" && $reason !== "未知原因,请重试") {
                if (config("mail.switch")) {
                    try {
                        Mail::raw("亲爱的" . config("mail.to.name") . ":\n\t有账户被风控,账号ID:" . $cookieData["data"]["id"], function ($message) {
                            $message->to(config("mail.to.address"))->subject("有账户过期了~");
                        });
                    } catch (Exception $e) {
                    }
                }

                Account::query()
                       ->find($cookieData["data"]["id"])
                       ->update([
                           "switch" => 0,
                           "reason" => $reason,
                       ]);
            }
            return ResponseController::errorFromMainServer($reason);
        } catch (GuzzleException $e) {
            return ResponseController::networkError("连接解析服务器");
        }

        if (!$response) return ResponseController::errorFromMainServer("未知原因");
        if ($response["code"] !== 200) return ResponseController::errorFromMainServer($response["message"] ?? "未知原因");
        $responseData = $response["data"];

        Account::query()
               ->find($cookieData["data"]["id"])
               ->update([
                   "last_use_at" => date("Y-m-d H:i:s")
               ]);

        if (config("94list.token_mode") && isset($request["token"]) && $request["token"] !== "") {
            $token   = Token::query()->firstWhere("name", $request["token"]);
            $user_id = -$token["id"];
        } else {
            $user_id = Auth::user()["id"] ?? -1;
        }

        foreach ($responseData as $responseDatum) {
            if (str_contains($responseDatum["url"], "dlna")) {
                if (config("94list.token_mode") && isset($token) && $token["expired_at"] === null) {
                    $token->update([
                        "expired_at" => now()->addDays($token["day"])
                    ]);
                }

                RecordController::addRecord([
                    "ip"                => UtilsController::getIp(),
                    "fs_id"             => $responseDatum["fs_id"] ?? 0,
                    "filename"          => $responseDatum["filename"],
                    "user_id"           => $user_id,
                    "account_id"        => $cookieData["data"]["id"],
                    "normal_account_id" => 0,
                    "size"              => FileList::query()->firstWhere("fs_id", $responseDatum["fs_id"] ?? 0)["size"] ?? 0,
                    "ua"                => $ua,
                    "url"               => $responseDatum["url"]
                ]);
            } else if (str_contains($responseDatum["url"], "风控") || str_contains($responseDatum["url"], "invalid")) {
                if (config("mail.switch")) {
                    try {
                        Mail::raw("亲爱的" . config("mail.to.name") . ":\n\t有账户被风控,账号ID:" . $responseDatum["cookie_id"], function ($message) {
                            $message->to(config("mail.to.address"))->subject("有账户过期了~");
                        });
                    } catch (Exception $e) {
                    }
                }

                Account::query()
                       ->find($cookieData["data"]["id"])
                       ->update([
                           "switch" => 0,
                           "reason" => $responseDatum["url"],
                       ]);
            }
        }

        return ResponseController::success($responseData);
    }

    public function getDownloadLinksByShare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            "randsk"   => "required|string",
            "uk"       => "required|numeric",
            "shareid"  => "required|numeric",
            "fs_ids"   => "required|array",
            "fs_ids.*" => "required|numeric",
            "url"      => "required|string"
        ]);

        if ($validator->fails()) return ResponseController::paramsError();

        $ua = config("94list.user_agent");

        $json = [
            "type"     => 2,
            "fsidlist" => $request["fs_ids"],
            "code"     => config("94list.code"),
            "randsk"   => $request["randsk"],
            "uk"       => $request["uk"],
            "shareid"  => $request["shareid"],
            "ua"       => $ua,
            "url"      => $request["url"]
        ];

        if ($request["vcode_input"] && $request["vcode_input"] !== "") {
            $validator = Validator::make($request->all(), [
                "vcode_input" => "required|string",
                "vcode_str"   => "required|string"
            ]);

            if ($validator->fails()) return ResponseController::paramsError();

            $json["vcode_input"] = $request["vcode_input"];
            $json["vcode_str"]   = $request["vcode_str"];
        }

        // 插入会员账号
        $json["cookie"] = [];
        for ($i = 0; $i < count($request["fs_ids"]); $i++) {
            $cookie     = self::getRandomCookie();
            $cookieData = $cookie->getData(true);
            if ($cookieData["code"] !== 200) return $cookie;
            $json["cookie"][] = [
                "id"     => $cookieData["data"]["id"],
                "cookie" => $cookieData["data"]["cookie"]
            ];
        }

        try {
            $http     = new Client();
            $res      = $http->post(config("94list.main_server") . "/api/parseUrl", ["json" => $json]);
            $response = JSON::decode($res->getBody()->getContents());
        } catch (RequestException $e) {
            $response = JSON::decode($e->getResponse()->getBody()->getContents());
            $reason   = $response["message"] ?? "未知原因,请重试";
            if (str_contains($reason, "风控")) {
                if (config("mail.switch")) {
                    try {
                        Mail::raw("亲爱的" . config("mail.to.name") . ":\n\t有账户被风控,账号ID:" . $json["cookie"][0]["id"], function ($message) {
                            $message->to(config("mail.to.address"))->subject("有账户过期了~");
                        });
                    } catch (Exception $e) {
                    }
                }

                Account::query()
                       ->find($json["cookie"][0]["id"])
                       ->update([
                           "switch" => 0,
                           "reason" => $reason,
                       ]);
            }
            return ResponseController::errorFromMainServer($reason);
        } catch (GuzzleException $e) {
            return ResponseController::networkError("连接解析服务器");
        }

        if (!$response) return ResponseController::errorFromMainServer("未知原因");
        if ($response["code"] !== 200) return ResponseController::errorFromMainServer($response["message"] ?? "未知原因");
        $responseData = $response["data"];

        if (config("94list.token_mode") && isset($request["token"]) && $request["token"] !== "") {
            $token   = Token::query()->firstWhere("name", $request["token"]);
            $user_id = -$token["id"];
        } else {
            $user_id = Auth::user()["id"] ?? -1;
        }

        foreach ($responseData as $responseDatum) {
            if (isset($responseDatum["msg"]) && $responseDatum["msg"] === "获取成功") {
                Account::query()
                       ->find($responseDatum["cookie_id"])
                       ->update([
                           "last_use_at" => date("Y-m-d H:i:s")
                       ]);

                if (config("94list.token_mode") && isset($token) && $token["expired_at"] === null) {
                    $token->update([
                        "expired_at" => now()->addDays($token["day"])
                    ]);
                }

                RecordController::addRecord([
                    "ip"                => UtilsController::getIp(),
                    "fs_id"             => $responseDatum["fs_id"] ?? 0,
                    "filename"          => $responseDatum["filename"],
                    "user_id"           => $user_id,
                    "account_id"        => $responseDatum["cookie_id"],
                    "normal_account_id" => 0,
                    "size"              => FileList::query()->firstWhere("fs_id", $responseDatum["fs_id"] ?? 0)["size"] ?? 0,
                    "ua"                => $ua,
                    "url"               => $responseDatum["url"]
                ]);
            } else if (str_contains($responseDatum["url"], "风控") || str_contains($responseDatum["url"], "invalid")) {
                if (config("mail.switch")) {
                    try {
                        Mail::raw("亲爱的" . config("mail.to.name") . ":\n\t有账户被风控,账号ID:" . $responseDatum["cookie_id"], function ($message) {
                            $message->to(config("mail.to.address"))->subject("有账户过期了~");
                        });
                    } catch (Exception $e) {
                    }
                }

                Account::query()
                       ->find($responseDatum["cookie_id"])
                       ->update([
                           "switch" => 0,
                           "reason" => $responseDatum["url"],
                       ]);
            }
        }

        return ResponseController::success(
            collect($responseData)->map(fn($v) => [
                "url"      => $v["url"],
                "urls"     => $v["urls"] ?? $v["url"],
                "filename" => $v["filename"],
                "fs_id"    => $v["fs_id"]
            ])
        );
    }

    public function getDownloadLinksByShareV2(Request $request, $parse_mode)
    {
        $validator = Validator::make($request->all(), [
            "fs_ids"   => "required|array",
            "fs_ids.*" => "required|numeric",
            "url"      => "required|string",
            "surl"     => "required|string",
            "dir"      => "required|string",
            "pwd"      => "string",
        ]);

        if ($validator->fails()) return ResponseController::paramsError();

        $ua = config("94list.user_agent");

        $json = [
            "type"     => $parse_mode,
            "fsidlist" => $request["fs_ids"],
            "code"     => config("94list.code"),
            "ua"       => $ua,
            "url"      => $request["url"],
            "surl"     => $request["surl"],
            "dir"      => $request["dir"],
            "pwd"      => $request["pwd"],
        ];

        // 插入会员账号
        $json["cookie"] = [];
        for ($i = 0; $i < count($request["fs_ids"]); $i++) {
            $cookie     = self::getRandomCookie();
            $cookieData = $cookie->getData(true);
            if ($cookieData["code"] !== 200) return $cookie;
            $json["cookie"][] = [
                "id"     => $cookieData["data"]["id"],
                "cookie" => $cookieData["data"]["cookie"]
            ];
        }

        try {
            $http     = new Client();
            $res      = $http->post(config("94list.main_server") . "/api/parseUrl", ["json" => $json]);
            $response = JSON::decode($res->getBody()->getContents());
        } catch (RequestException $e) {
            $response = JSON::decode($e->getResponse()->getBody()->getContents());
            $reason   = $response["message"] ?? "未知原因,请重试";
            return ResponseController::errorFromMainServer($reason);
        } catch (GuzzleException $e) {
            return ResponseController::networkError("连接解析服务器");
        }

        if (!$response) return ResponseController::errorFromMainServer("未知原因");
        if ($response["code"] !== 200) return ResponseController::errorFromMainServer($response["message"] ?? "未知原因");
        $responseData = $response["data"];

        if (config("94list.token_mode") && isset($request["token"]) && $request["token"] !== "") {
            $token   = Token::query()->firstWhere("name", $request["token"]);
            $user_id = -$token["id"];
        } else {
            $user_id = Auth::user()["id"] ?? -1;
        }

        foreach ($responseData as $responseDatum) {
            if (isset($responseDatum["msg"]) && $responseDatum["msg"] === "获取成功") {
                Account::query()
                       ->find($responseDatum["cookie_id"])
                       ->update([
                           "last_use_at" => date("Y-m-d H:i:s")
                       ]);

                if (isset($token) && $token["expired_at"] === null) {
                    $token->update([
                        "expired_at" => now()->addDays($token["day"])
                    ]);
                }

                RecordController::addRecord([
                    "ip"                => UtilsController::getIp(),
                    "fs_id"             => $responseDatum["fs_id"] ?? 0,
                    "filename"          => $responseDatum["filename"],
                    "user_id"           => $user_id,
                    "account_id"        => $responseDatum["cookie_id"],
                    "normal_account_id" => 0,
                    "size"              => FileList::query()->firstWhere("fs_id", $responseDatum["fs_id"] ?? 0)["size"] ?? 0,
                    "ua"                => $ua,
                    "url"               => $responseDatum["url"]
                ]);
            } else if (str_contains($responseDatum["url"], "风控") || str_contains($responseDatum["url"], "invalid")) {
                if (config("mail.switch")) {
                    try {
                        Mail::raw("亲爱的" . config("mail.to.name") . ":\n\t有账户被风控,账号ID:" . $responseDatum["cookie_id"], function ($message) {
                            $message->to(config("mail.to.address"))->subject("有账户过期了~");
                        });
                    } catch (Exception $e) {
                    }
                }

                Account::query()
                       ->find($responseDatum["cookie_id"])
                       ->update([
                           "switch" => 0,
                           "reason" => $responseDatum["url"],
                       ]);
            }
        }

        return ResponseController::success(
            collect($responseData)->map(function ($v) {
                $arr = [
                    "url"      => $v["url"],
                    "filename" => $v["filename"],
                    "fs_id"    => $v["fs_id"]
                ];
                if (isset($v["urls"])) $arr["urls"] = $v["urls"];
                return $arr;
            })
        );
    }
}